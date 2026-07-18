<?php

return [
    'auth' => [
        'client_otp_stub_enabled' => env('KLINOMANIA_CLIENT_OTP_STUB_ENABLED', false),
        'client_otp_stub_code' => env('KLINOMANIA_CLIENT_OTP_STUB_CODE', '1111'),
        'cleaner_code_stub_enabled' => env('KLINOMANIA_CLEANER_CODE_STUB_ENABLED', true),
        'cleaner_code_stub_code' => env('KLINOMANIA_CLEANER_CODE_STUB_CODE', '111111'),
    ],
    'admin' => [
        'email' => env('KLINOMANIA_ADMIN_EMAIL', 'admin@klinomania.local'),
        'password' => env('KLINOMANIA_ADMIN_PASSWORD', 'password'),
    ],
];
