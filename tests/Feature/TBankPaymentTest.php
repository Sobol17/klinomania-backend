<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Modules\Payments\Contracts\TBankGateway;
use App\Modules\Payments\Exceptions\TBankGatewayException;
use App\Modules\Payments\Support\TBankToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.tbank.terminal_key', 'TBankTest');
    config()->set('services.tbank.password', 'secret');
    config()->set('services.tbank.link_ttl_minutes', 1440);
});

test('client receives a reusable T-Bank payment URL only for their awaiting-payment order', function () {
    [$client, $order] = awaitingPaymentOrder();
    app()->bind(TBankGateway::class, fn () => new class implements TBankGateway
    {
        public function initialize(PaymentAttempt $attempt, string $description): array
        {
            return ['payment_id' => '700031849', 'payment_url' => 'https://securepay.tinkoff.ru/new/abc', 'provider_status' => 'NEW'];
        }
    });
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/orders/{$order->public_id}/payment")
        ->assertOk()
        ->assertJsonPath('data.payment_url', 'https://securepay.tinkoff.ru/new/abc')
        ->assertJsonPath('data.status', 'pending');
    $this->postJson("/api/v1/client/orders/{$order->public_id}/payment")
        ->assertOk()
        ->assertJsonPath('data.payment_url', 'https://securepay.tinkoff.ru/new/abc');

    expect(PaymentAttempt::query()->count())->toBe(1)
        ->and(PaymentAttempt::query()->sole()->amount)->toBe(770000);
});

test('payment provider failure is logged with order context', function () {
    [$client, $order] = awaitingPaymentOrder();
    app()->bind(TBankGateway::class, fn () => new class implements TBankGateway
    {
        public function initialize(PaymentAttempt $attempt, string $description): array
        {
            throw new TBankGatewayException('Provider returned error code 7.');
        }
    });
    Log::spy();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/orders/{$order->public_id}/payment")
        ->assertStatus(502)
        ->assertJsonPath('code', 'payment_provider_unavailable');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Payment initialization failed.'
            && $context['order_public_id'] === $order->public_id
            && $context['exception'] instanceof TBankGatewayException);
});

test('confirmed signed notification completes the matching order exactly once', function () {
    [, $order] = awaitingPaymentOrder();
    $attempt = PaymentAttempt::query()->create([
        'cleaning_order_id' => $order->id, 'provider' => 'tbank', 'external_order_id' => 'pay_01J2QM1R7H7YV9JH1KACD6ZK3R',
        'amount' => 770000, 'currency' => 'RUB', 'status' => 'pending', 'payment_url' => 'https://example.test/pay', 'expires_at' => now()->addDay(),
    ]);
    $payload = [
        'TerminalKey' => 'TBankTest', 'OrderId' => $attempt->external_order_id, 'Success' => true,
        'Status' => 'CONFIRMED', 'PaymentId' => '700031849', 'ErrorCode' => '0', 'Amount' => 770000,
    ];
    $payload['Token'] = TBankToken::make($payload, 'secret');

    $this->postJson('/api/v1/payments/tbank/notifications', $payload)->assertOk()->assertSeeText('OK');
    $this->postJson('/api/v1/payments/tbank/notifications', $payload)->assertOk()->assertSeeText('OK');

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($attempt->refresh()->status)->toBe('confirmed')
        ->and($attempt->provider_payment_id)->toBe('700031849');
});

test('notification with an invalid signature does not change an order', function () {
    [, $order] = awaitingPaymentOrder();
    $attempt = PaymentAttempt::query()->create([
        'cleaning_order_id' => $order->id, 'provider' => 'tbank', 'external_order_id' => 'pay_01J2QM1R7H7YV9JH1KACD6ZK3S',
        'amount' => 770000, 'currency' => 'RUB', 'status' => 'pending',
    ]);

    $this->postJson('/api/v1/payments/tbank/notifications', [
        'TerminalKey' => 'TBankTest', 'OrderId' => $attempt->external_order_id, 'Success' => true,
        'Status' => 'CONFIRMED', 'PaymentId' => '700031850', 'ErrorCode' => '0', 'Amount' => 770000, 'Token' => 'invalid',
    ])->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($attempt->refresh()->status)->toBe('pending');
});

function awaitingPaymentOrder(): array
{
    $client = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create(['name' => 'Базовая', 'slug' => fake()->unique()->slug(), 'base_price' => 7700]);
    $order = CleaningOrder::query()->create([
        'public_id' => (string) Str::ulid(), 'client_id' => $client->id, 'cleaning_service_id' => $service->id,
        'status' => OrderStatus::AwaitingPayment, 'address' => 'Иркутск, Ленина, 1', 'total_price' => 7700,
    ]);

    return [$client, $order];
}
