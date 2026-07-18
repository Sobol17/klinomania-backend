<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
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

    public function cancel(Request $request, CleaningOrder $order, OrderWorkflow $workflow): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);

        $order = $workflow->cancel($order, $request->user());

        return response()->json(['data' => $order->refresh()]);
    }
}
