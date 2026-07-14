<?php

use App\Enums\UserRole;
use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('client otp is random by default', function () {
    config()->set('klinomania.auth.client_otp_stub_enabled', false);
    config()->set('services.notisend.base_url', 'https://sms.notisend.ru');
    config()->set('services.notisend.project', 'klinomania');
    config()->set('services.notisend.api_key', 'test-api-key');

    Http::fake([
        'sms.notisend.ru/api/message/send*' => Http::response(['status' => 'success']),
    ]);

    $this->postJson('/api/v1/client/auth/request-code', [
        'phone' => '+79990000000',
    ])->assertOk();

    Http::assertSent(function (Request $request) {
        $payload = authMultipartPayload($request);
        $prefix = 'Klinomania: Ваш код для входа: ';

        if (! str_starts_with($payload['message'], $prefix)) {
            return false;
        }

        $code = substr($payload['message'], strlen($prefix));

        return preg_match('/^\d{4}$/', $code) === 1 && $code !== '1111';
    });
});

test('client is created only after verifying stub code', function () {
    config()->set('klinomania.auth.client_otp_stub_enabled', true);
    config()->set('klinomania.auth.client_otp_stub_code', '1111');
    config()->set('services.notisend.base_url', 'https://sms.notisend.ru');
    config()->set('services.notisend.project', 'klinomania');
    config()->set('services.notisend.api_key', 'test-api-key');

    Http::fake([
        'sms.notisend.ru/api/message/send*' => Http::response(['status' => 'success']),
    ]);

    $phone = '+79990000001';

    $this->postJson('/api/v1/client/auth/request-code', [
        'phone' => $phone,
    ])->assertOk();

    expect(User::query()->where('phone', $phone)->exists())->toBeFalse();
    expect(AuthCode::query()->where('phone', $phone)->exists())->toBeTrue();

    Http::assertSent(function (Request $request) {
        $payload = authMultipartPayload($request);

        return $payload['message'] === 'Klinomania: Ваш код для входа: 1111';
    });

    $this->postJson('/api/v1/client/auth/verify-code', [
        'phone' => $phone,
        'code' => '1111',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user']);

    $user = User::query()->where('phone', $phone)->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Client)
        ->and($user->clientProfile)->not->toBeNull();
});

test('client stub rejects invalid code', function () {
    config()->set('klinomania.auth.client_otp_stub_enabled', true);
    config()->set('klinomania.auth.client_otp_stub_code', '1111');
    config()->set('services.notisend.api_key', null);

    $phone = '+79990000002';

    $this->postJson('/api/v1/client/auth/request-code', [
        'phone' => $phone,
    ])->assertOk();

    $this->postJson('/api/v1/client/auth/verify-code', [
        'phone' => $phone,
        'code' => '2222',
    ])->assertUnprocessable();

    expect(User::query()->where('phone', $phone)->exists())->toBeFalse();
});

test('cleaner can login with stub code', function () {
    config()->set('klinomania.auth.cleaner_code_stub_enabled', true);
    config()->set('klinomania.auth.cleaner_code_stub_code', '111111');

    $cleaner = User::factory()->create([
        'phone' => '+79990000003',
        'role' => UserRole::Cleaner,
    ]);

    $cleaner->cleanerProfile()->create([
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/cleaner/auth/login', [
        'phone' => '+79990000003',
        'code' => '111111',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

test('cleaner stub rejects invalid code', function () {
    config()->set('klinomania.auth.cleaner_code_stub_enabled', true);
    config()->set('klinomania.auth.cleaner_code_stub_code', '111111');

    $cleaner = User::factory()->create([
        'phone' => '+79990000004',
        'role' => UserRole::Cleaner,
    ]);

    $cleaner->cleanerProfile()->create([
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/cleaner/auth/login', [
        'phone' => '+79990000004',
        'code' => '222222',
    ])->assertUnprocessable();
});

function authMultipartPayload(Request $request): array
{
    $payload = [];

    foreach ($request->data() as $part) {
        $payload[$part['name']] = $part['contents'];
    }

    return $payload;
}
