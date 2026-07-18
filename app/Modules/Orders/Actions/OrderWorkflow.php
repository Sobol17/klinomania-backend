<?php

namespace App\Modules\Orders\Actions;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Models\User;
use App\Modules\Notifications\Events\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class OrderWorkflow
{
    public function confirm(CleaningOrder $order): CleaningOrder
    {
        $order = DB::transaction(function () use ($order): CleaningOrder {
            $order = $this->lockedOrder($order);
            $this->requireStatus($order, OrderStatus::Processing);
            $order->forceFill(['status' => OrderStatus::Confirmed])->save();

            return $order;
        });

        $this->statusChanged($order);

        return $order;
    }

    public function accept(CleaningOrder $order, User $cleaner): CleaningOrder
    {
        $order = DB::transaction(function () use ($order, $cleaner): CleaningOrder {
            $order = $this->lockedOrder($order);
            $this->requireStatus($order, OrderStatus::Confirmed);
            if ($order->cleaners()->whereKey($cleaner->id)->exists()) {
                $this->conflict('Cleaner has already joined this order.');
            }

            $requiredCleaners = $order->service->required_cleaners;
            if ($order->cleaners()->count() >= $requiredCleaners) {
                $this->conflict('The order team is already complete.');
            }

            $order->cleaners()->attach($cleaner->id, ['accepted_at' => now()]);
            if ($order->cleaners()->count() === $requiredCleaners) {
                $order->forceFill([
                    'status' => $requiredCleaners === 1 ? OrderStatus::InProgress : OrderStatus::TeamFormed,
                ])->save();
                if ($requiredCleaners === 1) {
                    $order->cleaners()->updateExistingPivot($cleaner->id, ['started_at' => now()]);
                }
            }

            return $order;
        });

        if ($order->wasChanged('status')) {
            $this->statusChanged($order);
        }

        return $order;
    }

    public function start(CleaningOrder $order, User $cleaner): CleaningOrder
    {
        $order = DB::transaction(function () use ($order, $cleaner): CleaningOrder {
            $order = $this->lockedOrder($order);
            if (! in_array($order->status, [OrderStatus::TeamFormed, OrderStatus::InProgress], true)) {
                $this->conflict('The order cannot be started in its current status.');
            }
            $member = $order->cleaners()->whereKey($cleaner->id)->first();
            if ($member === null) {
                $this->conflict('Only an assigned cleaner can start this order.');
            }

            if ($member->pivot->started_at === null) {
                $order->cleaners()->updateExistingPivot($cleaner->id, ['started_at' => now()]);
            }
            if ($order->status === OrderStatus::TeamFormed) {
                $order->forceFill(['status' => OrderStatus::InProgress])->save();
            }

            return $order;
        });

        if ($order->wasChanged('status')) {
            $this->statusChanged($order);
        }

        return $order;
    }

    public function complete(CleaningOrder $order, User $cleaner): CleaningOrder
    {
        $order = DB::transaction(function () use ($order, $cleaner): CleaningOrder {
            $order = $this->lockedOrder($order);
            $this->requireStatus($order, OrderStatus::InProgress);
            $member = $order->cleaners()->whereKey($cleaner->id)->first();
            if ($member === null || $member->pivot->started_at === null) {
                $this->conflict('Only a cleaner who started the order can complete it.');
            }
            if ($order->service->checklistItems()
                ->whereDoesntHave('orderChecklistItems', fn ($query) => $query
                    ->where('cleaning_order_id', $order->id)
                    ->whereNotNull('completed_at'))
                ->exists()) {
                $this->checklistIncomplete();
            }
            if ($order->lineItems()
                ->where('kind', 'extra_option')
                ->whereDoesntHave('extraChecklistItem', fn ($query) => $query->whereNotNull('completed_at'))
                ->exists()) {
                $this->checklistIncomplete();
            }

            $order->cleaners()->updateExistingPivot($cleaner->id, ['completed_at' => now()]);
            $order->forceFill(['status' => OrderStatus::AwaitingPayment])->save();

            return $order;
        });

        $this->statusChanged($order);

        return $order;
    }

    public function cancel(CleaningOrder $order, User $client): CleaningOrder
    {
        return DB::transaction(function () use ($order, $client): CleaningOrder {
            $order = $this->lockedOrder($order);
            if ($order->client_id !== $client->id) {
                abort(response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403));
            }
            if (! in_array($order->status, [OrderStatus::Processing, OrderStatus::Confirmed], true)) {
                $this->conflict('The order can no longer be cancelled.');
            }
            $order->forceFill(['status' => OrderStatus::Cancelled])->save();

            return $order;
        });
    }

    private function lockedOrder(CleaningOrder $order): CleaningOrder
    {
        return CleaningOrder::query()->with('service')->lockForUpdate()->findOrFail($order->getKey());
    }

    private function requireStatus(CleaningOrder $order, OrderStatus $status): void
    {
        if ($order->status !== $status) {
            $this->conflict('The order cannot transition from its current status.');
        }
    }

    private function conflict(string $message): never
    {
        abort(response()->json(['message' => $message, 'code' => 'invalid_order_transition'], 409));
    }

    private function checklistIncomplete(): never
    {
        abort(response()->json(['message' => 'All checklist items must be completed before finishing the order.', 'code' => 'checklist_incomplete'], 409));
    }

    private function statusChanged(CleaningOrder $order): void
    {
        Event::dispatch(new OrderStatusChanged($order->id, $order->status));
    }
}
