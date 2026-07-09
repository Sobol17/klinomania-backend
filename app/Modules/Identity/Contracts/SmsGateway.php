<?php

namespace App\Modules\Identity\Contracts;

interface SmsGateway
{
    public function send(string $phone, string $message): void;
}
