<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CleaningOrder;
use App\Modules\Payments\Actions\CreateTBankPayment;
use App\Modules\Payments\Exceptions\TBankGatewayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function store(Request $request, CleaningOrder $order, CreateTBankPayment $payments): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Client, 403);
        abort_unless($order->client_id === $request->user()->id, 403);

        try {
            $payment = $payments->execute($order);
        } catch (TBankGatewayException $exception) {
            return response()->json(['message' => 'Unable to initialize payment.', 'code' => 'payment_provider_unavailable'], 502);
        }

        return response()->json(['data' => [
            'id' => $payment->external_order_id,
            'payment_url' => $payment->payment_url,
            'expires_at' => $payment->expires_at,
            'status' => $payment->status,
        ]]);
    }
}
