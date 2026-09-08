<?php

namespace App\Modules\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\User;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class AssignCleaner
{
    public function assign(CleaningOrder $order, User $cleaner): CleaningOrder
    {
        $order = DB::transaction(function () use ($order, $cleaner): CleaningOrder {
            $order = $this->lockedOrder($order);

            if ($order->status !== OrderStatus::Confirmed) {
                $this->reject('A cleaner can only be assigned to a confirmed order.');
            }
            if ($cleaner->role !== UserRole::Cleaner || ! $cleaner->cleanerProfile?->is_active) {
                $this->reject('Only an active cleaner can be assigned to an order.');
            }
            if ($order->cleaners()->whereKey($cleaner->getKey())->exists()) {
                $this->reject('The cleaner is already assigned to this order.');
            }

            $requiredCleaners = $order->service->required_cleaners;
            if ($order->cleaners()->count() >= $requiredCleaners) {
                $this->reject("The order already has the required {$requiredCleaners} cleaners.");
            }

            $order->cleaners()->attach($cleaner->getKey(), ['accepted_at' => now()]);

            if ($order->cleaners()->count() === $requiredCleaners) {
                $order->forceFill([
                    'status' => $requiredCleaners === 1 ? OrderStatus::InProgress : OrderStatus::TeamFormed,
                ])->save();
            }

            return $order;
        });

        if ($order->wasChanged('status')) {
            Event::dispatch(new OrderStatusChanged($order->id, $order->status));
        }

        return $order;
    }

    public function remove(CleaningOrder $order, User $cleaner): CleaningOrder
    {
        $order = DB::transaction(function () use ($order, $cleaner): CleaningOrder {
            $order = $this->lockedOrder($order);

            if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::TeamFormed], true)) {
                $this->reject('A cleaner cannot be removed after work has started.');
            }

            $member = $order->cleaners()->whereKey($cleaner->getKey())->first();
            if ($member === null) {
                $this->reject('The cleaner is not assigned to this order.');
            }
            if ($member->pivot->started_at !== null) {
                $this->reject('A cleaner who has started work cannot be removed.');
            }

            $order->cleaners()->detach($cleaner->getKey());

            if ($order->status === OrderStatus::TeamFormed
                && $order->cleaners()->count() < $order->service->required_cleaners) {
                $order->forceFill(['status' => OrderStatus::Confirmed])->save();
            }

            return $order;
        });

        if ($order->wasChanged('status')) {
            Event::dispatch(new OrderStatusChanged($order->id, $order->status));
        }

        return $order;
    }

    private function lockedOrder(CleaningOrder $order): CleaningOrder
    {
        return CleaningOrder::query()->with('service')->lockForUpdate()->findOrFail($order->getKey());
    }

    private function reject(string $message): never
    {
        throw new InvalidOrderTransition($message);
    }
}
