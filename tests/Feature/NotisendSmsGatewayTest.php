<?php

use App\Modules\Identity\Gateways\NotisendSmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('notisend gateway sends sms through notisend api', function () {
    config()->set('services.notisend.base_url', 'https://sms.notisend.ru');
    config()->set('services.notisend.project', 'klinomania');
    config()->set('services.notisend.api_key', 'test-api-key');

    Http::fake([
        'sms.notisend.ru/api/message/send*' => Http::response([
            'status' => 'success',
            'recipients' => ['9859221254'],
            'messages_id' => [123],
        ]),
    ]);

    app(NotisendSmsGateway::class)->send('+7 (985) 922-12-54', 'Klinomania: Ваш код для входа: 1234');

    Http::assertSent(function (Request $request) {
        $payload = multipartPayload($request);

        return $request->method() === 'GET'
            && $request->url() === 'https://sms.notisend.ru/api/message/send'
            && $payload === [
                'project' => 'klinomania',
                'recipients' => '79859221254',
                'message' => 'Klinomania: Ваш код для входа: 1234',
                'apikey' => 'test-api-key',
            ];
    });
});

test('notisend gateway throws on api error response', function () {
    config()->set('services.notisend.base_url', 'https://sms.notisend.ru');
    config()->set('services.notisend.project', 'klinomania');
    config()->set('services.notisend.api_key', 'test-api-key');

    Http::fake([
        'sms.notisend.ru/api/message/send*' => Http::response([
            'status' => 'error',
            'error' => 7,
            'message' => 'not enough money',
        ]),
    ]);

    expect(fn () => app(NotisendSmsGateway::class)->send('79859221254', 'Klinomania: Ваш код для входа: 1234'))
        ->toThrow(RuntimeException::class, 'NotiSend SMS sending failed [7]: not enough money');
});

function multipartPayload(Request $request): array
{
    $payload = [];

    foreach ($request->data() as $part) {
        $payload[$part['name']] = $part['contents'];
    }

    return $payload;
}
