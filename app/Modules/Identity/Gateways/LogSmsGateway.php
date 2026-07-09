<?php

namespace App\Modules\Identity\Gateways;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS gateway stub called.', [
            'phone' => $phone,
            'message_length' => mb_strlen($message),
        ]);
    }
}
