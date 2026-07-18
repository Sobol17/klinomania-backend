<?php

namespace App\Modules\Payments\Gateways;

use App\Models\PaymentAttempt;
use App\Modules\Payments\Contracts\TBankGateway;
use App\Modules\Payments\Exceptions\TBankGatewayException;
use App\Modules\Payments\Support\TBankToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpTBankGateway implements TBankGateway
{
    public function initialize(PaymentAttempt $attempt, string $description): array
    {
        $payload = [
            'TerminalKey' => config('services.tbank.terminal_key'),
            'Amount' => $attempt->amount,
            'OrderId' => $attempt->external_order_id,
            'Description' => $description,
            'PayType' => 'O',
            'Language' => 'ru',
            'NotificationURL' => config('services.tbank.notification_url'),
            'RedirectDueDate' => $attempt->expires_at->format('Y-m-d\\TH:i:sP'),
            'DATA' => ['order_id' => (string) $attempt->order->public_id],
        ];
        $payload['Token'] = TBankToken::make($payload, (string) config('services.tbank.password'));

        try {
            $response = Http::acceptJson()->timeout(10)
                ->post(rtrim((string) config('services.tbank.base_url'), '/').'/v2/Init', $payload);
        } catch (ConnectionException $exception) {
            throw new TBankGatewayException('T-Bank payment service is unavailable.', previous: $exception);
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ($data['Success'] ?? false) !== true || empty($data['PaymentURL']) || empty($data['PaymentId'])) {
            throw new TBankGatewayException((string) ($data['Message'] ?? 'T-Bank could not initialize the payment.'));
        }

        return [
            'payment_id' => (string) $data['PaymentId'],
            'payment_url' => (string) $data['PaymentURL'],
            'provider_status' => (string) ($data['Status'] ?? 'NEW'),
        ];
    }
}
