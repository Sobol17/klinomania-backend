<?php

namespace App\Modules\Notifications\Contracts;

interface PushGateway
{
    /** @param array<string, string> $data */
    public function send(string $token, string $title, string $body, array $data): void;
}
