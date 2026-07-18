<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'notisend' => [
        'base_url' => env('NOTISEND_BASE_URL', 'https://sms.notisend.ru'),
        'project' => env('NOTISEND_PROJECT', 'klinomania'),
        'api_key' => env('NOTISEND_API_KEY', env('NOTISEND_TOKEN')),
    ],

    'tbank' => [
        'base_url' => env('TBANK_API_URL', 'https://securepay.tinkoff.ru'),
        'terminal_key' => env('TBANK_TERMINAL_KEY'),
        'password' => env('TBANK_PASSWORD'),
        'notification_url' => env('TBANK_NOTIFICATION_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/payments/tbank/notifications'),
        'link_ttl_minutes' => (int) env('TBANK_LINK_TTL_MINUTES', 1440),
    ],

];
