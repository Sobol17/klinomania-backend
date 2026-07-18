<?php

namespace App\Modules\Payments\Actions;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Models\PaymentAttempt;
use App\Modules\Payments\Contracts\TBankGateway;
use App\Modules\Payments\Exceptions\TBankGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTBankPayment
{
    public function __construct(private readonly TBankGateway $gateway) {}

    public function execute(CleaningOrder $order): PaymentAttempt
    {
        $attempt = DB::transaction(function () use ($order): PaymentAttempt {
            $order = CleaningOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== OrderStatus::AwaitingPayment) {
                abort(response()->json(['message' => 'The order is not awaiting payment.', 'code' => 'invalid_order_transition'], 409));
            }

            $activeAttempt = $order->paymentAttempts()
                ->where('provider', 'tbank')
                ->where('status', 'pending')
                ->whereNotNull('payment_url')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();
            if ($activeAttempt !== null) {
                return $activeAttempt;
            }

            return $order->paymentAttempts()->create([
                'provider' => 'tbank',
                'external_order_id' => 'pay_'.Str::ulid(),
                'amount' => $order->total_price * 100,
                'currency' => $order->currency,
                'expires_at' => now()->addMinutes((int) config('services.tbank.link_ttl_minutes')),
                'status' => 'creating',
            ]);
        });

        if ($attempt->status === 'pending') {
            return $attempt;
        }

        try {
            $result = $this->gateway->initialize($attempt->load('order'), 'Оплата заказа '.$attempt->order->public_id);
        } catch (TBankGatewayException $exception) {
            $attempt->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()])->save();
            throw $exception;
        }

        $attempt->forceFill([
            'provider_payment_id' => $result['payment_id'],
            'payment_url' => $result['payment_url'],
            'provider_status' => $result['provider_status'],
            'status' => 'pending',
        ])->save();

        return $attempt;
    }
}
