<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Models\OrderLineItem;
use App\Modules\Orders\Actions\OrderWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'cleaners.cleanerProfile'])
                ->where('client_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, CleaningOrder $order): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);
        abort_unless($order->client_id === $request->user()->id, 403);

        return response()->json([
            'data' => $this->detailResponse($order->load([
                'service',
                'cleaners.cleanerProfile',
                'addressSnapshot',
                'lineItems',
            ])),
        ]);
    }

    public function cancel(Request $request, CleaningOrder $order, OrderWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $order = $workflow->cancel($order, $request->user());

        return response()->json(['data' => $order->refresh()]);
    }

    /** @return array<string, mixed> */
    private function detailResponse(CleaningOrder $order): array
    {
        $lineItems = $order->lineItems->map(fn (OrderLineItem $item): array => [
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
            'currency' => $order->currency,
            'service' => $order->service_snapshot ?? [
                'id' => $order->service?->slug,
                'title' => $order->service?->name,
            ],
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
            'cleaners' => $order->cleaners->map(fn ($cleaner): array => [
                'id' => $cleaner->id,
                'name' => $cleaner->cleanerProfile?->name ?? $cleaner->name,
            ])->values(),
            'created_at' => $order->created_at,
        ];
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
