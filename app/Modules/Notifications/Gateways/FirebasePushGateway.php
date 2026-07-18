<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\PushGateway;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use RuntimeException;

class FirebasePushGateway implements PushGateway
{
    private ?Messaging $messaging = null;

    public function __construct(private readonly ?string $credentialsPath) {}

    public function send(string $token, string $title, string $body, array $data): void
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $this->messaging()->send($message);
    }

    private function messaging(): Messaging
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }

        if ($this->credentialsPath === null || ! is_file($this->credentialsPath)) {
            throw new RuntimeException('Firebase service-account credentials are not configured.');
        }

        return $this->messaging = (new Factory)
            ->withServiceAccount($this->credentialsPath)
            ->createMessaging();
    }
}
