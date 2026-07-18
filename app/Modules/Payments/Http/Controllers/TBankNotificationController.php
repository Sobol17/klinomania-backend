<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Payments\Support\TBankToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

class TBankNotificationController extends Controller
{
    public function store(Request $request): Response
    {
        $payload = $request->all();
        $token = $payload['Token'] ?? null;
        if (! is_string($token) || ! hash_equals(TBankToken::make($payload, (string) config('services.tbank.password')), $token)) {
            return response('Invalid token', 403);
        }
        if (($payload['TerminalKey'] ?? null) !== config('services.tbank.terminal_key')) {
            return response('Invalid terminal', 403);
        }

        $attempt = PaymentAttempt::query()->where('provider', 'tbank')->where('external_order_id', $payload['OrderId'] ?? null)->first();
        if ($attempt === null || (int) ($payload['Amount'] ?? -1) !== $attempt->amount) {
            return response('Unknown payment', 404);
        }

        $completedOrderId = DB::transaction(function () use ($attempt, $payload): ?int {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempt->forceFill([
                'provider_payment_id' => (string) ($payload['PaymentId'] ?? $attempt->provider_payment_id),
                'provider_status' => (string) ($payload['Status'] ?? ''),
                'error_code' => (string) ($payload['ErrorCode'] ?? ''),
                'error_message' => $payload['Message'] ?? null,
            ]);

            $success = filter_var($payload['Success'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isConfirmed = $success && ($payload['Status'] ?? null) === 'CONFIRMED';
            if ($isConfirmed) {
                $attempt->forceFill(['status' => 'confirmed', 'confirmed_at' => $attempt->confirmed_at ?? now()]);
            } elseif (! $success) {
                $attempt->forceFill(['status' => 'failed']);
            }
            $attempt->save();

            $order = $attempt->order()->lockForUpdate()->firstOrFail();
            if ($isConfirmed && $order->status === OrderStatus::AwaitingPayment) {
                $order->forceFill(['status' => OrderStatus::Completed])->save();

                return $order->id;
            }

            return null;
        });

        if ($completedOrderId !== null) {
            Event::dispatch(new OrderStatusChanged($completedOrderId, OrderStatus::Completed));
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
