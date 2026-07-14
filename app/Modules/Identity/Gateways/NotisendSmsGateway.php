<?php

namespace App\Modules\Identity\Gateways;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotisendSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        $baseUrl = config('services.notisend.base_url');
        $project = config('services.notisend.project');
        $apiKey = config('services.notisend.api_key');

        if (! $baseUrl || ! $project || ! $apiKey) {
            app(LogSmsGateway::class)->send($phone, $message);

            return;
        }

        $payload = [
            'project' => $project,
            'recipients' => $this->normalizePhone($phone),
            'message' => $message,
            'apikey' => $apiKey,
        ];

        $response = Http::acceptJson()
            ->asMultipart()
            ->send('GET', rtrim($baseUrl, '/').'/api/message/send', [
                'multipart' => $payload,
            ])
            ->throw();

        if ($response->json('status') === 'error') {
            throw new RuntimeException(sprintf(
                'NotiSend SMS sending failed [%s]: %s',
                $response->json('error', 'unknown'),
                $response->json('message', 'Unknown error.')
            ));
        }

        if ($response->json('status') !== 'success') {
            throw new RuntimeException('NotiSend SMS sending failed: unexpected response.');
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
