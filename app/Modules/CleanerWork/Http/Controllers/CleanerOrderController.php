<?php

namespace App\Modules\CleanerWork\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CleanerOrderController extends Controller
{
    public function available(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'client.clientProfile'])
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::AwaitingCleaner])
                ->latest()
                ->get(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'client.clientProfile'])
                ->where('cleaner_id', $request->user()->id)
                ->where('status', OrderStatus::Completed)
                ->latest()
                ->get(),
        ]);
    }

    public function accept(Request $request, CleaningOrder $order): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        abort_unless(in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingCleaner], true), 409);

        $order->forceFill([
            'cleaner_id' => $request->user()->id,
            'status' => OrderStatus::Accepted,
        ])->save();

        return response()->json(['data' => $order->refresh()->load('service')]);
    }

    public function start(Request $request, CleaningOrder $order): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        abort_unless($order->cleaner_id === $request->user()->id && $order->status === OrderStatus::Accepted, 409);

        $order->forceFill(['status' => OrderStatus::InProgress])->save();

        return response()->json(['data' => $order->refresh()->load('service')]);
    }

    public function complete(Request $request, CleaningOrder $order): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Cleaner, 403);
        abort_unless($order->cleaner_id === $request->user()->id && $order->status === OrderStatus::InProgress, 409);

        $order->forceFill(['status' => OrderStatus::Completed])->save();

        return response()->json(['data' => $order->refresh()->load('service')]);
    }
}
