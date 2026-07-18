<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Notifications\Contracts\PushGateway;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Notifications\Listeners\SendOrderStatusPush;
use App\Modules\Orders\Actions\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('a client can register one active push token', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/client/push-token', ['token' => 'first-token'])->assertNoContent();
    $this->postJson('/api/v1/client/push-token', ['token' => 'last-token'])->assertNoContent();

    expect($client->refresh()->clientProfile->fcm_token)->toBe('last-token');
});

test('a push token is transferred to the most recently authenticated client', function () {
    $firstClient = User::factory()->create(['role' => UserRole::Client]);
    $secondClient = User::factory()->create(['role' => UserRole::Client]);
    $firstClient->clientProfile()->create(['fcm_token' => 'shared-token']);
    Sanctum::actingAs($secondClient);

    $this->postJson('/api/v1/client/push-token', ['token' => 'shared-token'])->assertNoContent();

    expect($firstClient->refresh()->clientProfile->fcm_token)->toBeNull()
        ->and($secondClient->refresh()->clientProfile->fcm_token)->toBe('shared-token');
});

test('only clients can register a push token', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Cleaner]));

    $this->postJson('/api/v1/client/push-token', ['token' => 'token'])->assertForbidden();
});

test('a push token is required', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

    $this->postJson('/api/v1/client/push-token', [])->assertUnprocessable()->assertJsonValidationErrors('token');
});

test('server status changes publish a push event while client cancellation does not', function () {
    Event::fake([OrderStatusChanged::class]);
    [$client, $order] = orderForPushTests(OrderStatus::Processing);

    app(OrderWorkflow::class)->confirm($order);

    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event): bool => $event->orderId === $order->id && $event->status === OrderStatus::Confirmed);
    Event::fake([OrderStatusChanged::class]);
    app(OrderWorkflow::class)->cancel($order->refresh(), $client);
    Event::assertNotDispatched(OrderStatusChanged::class);
});

test('the status push includes the public order id and respects client preferences', function () {
    [$client, $order] = orderForPushTests(OrderStatus::Confirmed);
    $client->clientProfile()->create(['push_notifications_enabled' => true, 'fcm_token' => 'token']);
    $gateway = new class implements PushGateway
    {
        /** @var array<string, mixed>|null */
        public ?array $sent = null;

        public function send(string $token, string $title, string $body, array $data): void
        {
            $this->sent = compact('token', 'title', 'body', 'data');
        }
    };
    app()->instance(PushGateway::class, $gateway);

    app(SendOrderStatusPush::class)->handle(new OrderStatusChanged($order->id, OrderStatus::Confirmed));

    expect($gateway->sent)->toBe([
        'token' => 'token',
        'title' => 'Статус заявки изменён',
        'body' => "Заявка {$order->public_id}: Подтверждена",
        'data' => ['order_id' => $order->public_id],
    ]);

    $client->clientProfile()->update(['push_notifications_enabled' => false]);
    $gateway->sent = null;
    app(SendOrderStatusPush::class)->handle(new OrderStatusChanged($order->id, OrderStatus::Confirmed));
    expect($gateway->sent)->toBeNull();
});

/** @return array{User, CleaningOrder} */
function orderForPushTests(OrderStatus $status): array
{
    $client = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create(['name' => 'Стандарт', 'slug' => 'standard', 'base_price' => 1000]);
    $order = CleaningOrder::query()->create([
        'public_id' => fake()->unique()->bothify('01J2QM1R7H7YV9JH1KACD6ZK?#'),
        'client_id' => $client->id,
        'cleaning_service_id' => $service->id,
        'status' => $status,
        'address' => 'Иркутск, Ленина, 1',
        'scheduled_at' => now()->addDay(),
        'total_price' => 1000,
    ]);

    return [$client, $order];
}
