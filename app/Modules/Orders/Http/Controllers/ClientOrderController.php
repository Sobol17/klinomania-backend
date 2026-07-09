<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        return response()->json([
            'data' => CleaningOrder::query()
                ->with(['service', 'cleaner.cleanerProfile'])
                ->where('client_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $data = $request->validate([
            'cleaning_service_id' => ['required', 'integer', 'exists:cleaning_services,id'],
            'address' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = CleaningService::query()->where('is_active', true)->findOrFail($data['cleaning_service_id']);

        $order = CleaningOrder::create([
            'client_id' => $request->user()->id,
            'cleaning_service_id' => $service->id,
            'status' => OrderStatus::Pending,
            'address' => $data['address'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'comment' => $data['comment'] ?? null,
            'total_price' => $service->base_price,
        ]);

        return response()->json(['data' => $order->load('service')], 201);
    }
}
