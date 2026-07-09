<?php

namespace App\Modules\Identity\Gateways;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;

class NoticendSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        $baseUrl = config('services.noticend.base_url');
        $token = config('services.noticend.token');

        if (! $baseUrl || ! $token) {
            app(LogSmsGateway::class)->send($phone, $message);

            return;
        }

        Http::withToken($token)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/').'/sms', [
                'phone' => $phone,
                'message' => $message,
                'sender' => config('services.noticend.sender'),
            ])
            ->throw();
    }
}
