<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Str;

class Login extends BaseLogin
{
    protected function getRateLimitKey($method, $component = null): string
    {
        $email = Str::lower(trim((string) ($this->data['email'] ?? '')));

        return 'admin-login:'.hash('sha256', $email.'|'.request()->ip());
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null) {
            $this->clearRateLimiter('authenticate');
        }

        return $response;
    }
}
