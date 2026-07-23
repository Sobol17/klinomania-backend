<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\PushGateway;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebasePushGateway implements PushGateway
{
    public function __construct(private readonly Messaging $messaging) {}

    public function send(string $token, string $title, string $body, array $data): void
    {
        $message = CloudMessage::new()
            ->withToken($token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $this->messaging->send($message);
    }
}
