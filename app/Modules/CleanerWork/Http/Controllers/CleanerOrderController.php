<?php

namespace App\Modules\CleanerWork\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Modules\Orders\Actions\OrderWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CleanerOrderController extends Controller
{
    public function available(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'client.clientProfile', 'lineItems'])
                ->withCount('cleaners')
                ->where('status', OrderStatus::Confirmed)
                ->whereRaw('(select count(*) from cleaning_order_cleaners where cleaning_order_cleaners.cleaning_order_id = cleaning_orders.id) < (select required_cleaners from cleaning_services where cleaning_services.id = cleaning_orders.cleaning_service_id)')
                ->latest()
                ->get()
                ->map(fn (CleaningOrder $order): array => $this->listResponse($order, $order->cleaners_count + 1))
                ->values(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'client.clientProfile', 'lineItems'])
                ->withCount('cleaners')
                ->whereHas('cleaners', fn ($query) => $query->whereKey($request->user()->id))
                ->whereIn('status', [OrderStatus::AwaitingPayment, OrderStatus::Completed])
                ->latest()
                ->get()
                ->map(fn (CleaningOrder $order): array => $this->listResponse($order, $order->cleaners_count))
                ->values(),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'client.clientProfile', 'lineItems'])
                ->withCount('cleaners')
                ->whereHas('cleaners', fn ($query) => $query->whereKey($request->user()->id))
                ->whereIn('status', [OrderStatus::TeamFormed, OrderStatus::InProgress, OrderStatus::AwaitingPayment, OrderStatus::Completed])
                ->latest()
                ->get()
                ->map(fn (CleaningOrder $order): array => $this->listResponse($order, $order->cleaners_count))
                ->values(),
        ]);
    }

    public function show(Request $request, CleaningOrder $order): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        if (! $order->cleaners()->whereKey($request->user()->id)->exists()) {
            return response()->json(['message' => 'Forbidden.', 'code' => 'forbidden'], 403);
        }

        return response()->json(['data' => $this->detailResponse($order->load(['service', 'addressSnapshot', 'lineItems'])->loadCount('cleaners'))]);
    }

    public function accept(Request $request, CleaningOrder $order, OrderWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        $order = $workflow->accept($order, $request->user());

        return response()->json(['data' => $order->refresh()->load('service')]);
    }

    public function start(Request $request, CleaningOrder $order, OrderWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        $order = $workflow->start($order, $request->user());

        return response()->json(['data' => $order->refresh()->load('service')]);
    }

    public function complete(Request $request, CleaningOrder $order, OrderWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        $order = $workflow->complete($order, $request->user());

        return response()->json(['data' => $order->refresh()->load('service')]);
    }

    private function detailResponse(CleaningOrder $order): array
    {
        $lineItems = $order->lineItems->map(fn ($item): array => [
            'kind' => $item->kind,
            'option_id' => $item->source_option_id,
            'title' => $item->title,
            'amount' => $item->amount,
        ])->values();
        $address = $order->addressSnapshot;

        return [
            'id' => $order->public_id,
            'status' => $order->status->value,
            'status_label' => $this->statusLabel($order->status),
            'scheduled_at' => $order->scheduled_at,
            'total_price' => $order->total_price,
            'cleaner_earnings' => $order->cleanerEarnings($order->cleaners_count),
            'currency' => $order->currency,
            'service' => $order->service_snapshot ?? ['id' => $order->service?->slug, 'title' => $order->service?->name],
            'address' => [
                'full_address' => $address?->full_address ?? $order->address,
                'entrance' => $address?->entrance,
                'floor' => $address?->floor,
                'apartment' => $address?->apartment,
                'intercom' => $address?->intercom,
                'comment' => $address?->comment ?? $order->comment,
            ],
            'line_items' => $lineItems,
            'extra_options' => $lineItems->where('kind', 'extra_option')->values(),
        ];
    }

    private function listResponse(CleaningOrder $order, int $cleanerCount): array
    {
        $data = $order->toArray();
        unset($data['line_items'], $data['cleaners_count']);

        return [...$data, 'cleaner_earnings' => $order->cleanerEarnings($cleanerCount)];
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Processing => 'В обработке',
            OrderStatus::Confirmed => 'Подтверждена',
            OrderStatus::TeamFormed => 'Команда сформирована',
            OrderStatus::InProgress => 'В работе',
            OrderStatus::AwaitingPayment => 'Ожидает оплаты',
            OrderStatus::Completed => 'Выполнена',
            OrderStatus::Cancelled => 'Отменена',
        };
    }
}
