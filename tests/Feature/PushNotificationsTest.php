<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Notifications\Contracts\PushGateway;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Notifications\Gateways\FirebasePushGateway;
use App\Modules\Notifications\Listeners\SendOrderStatusPush;
use App\Modules\Notifications\Services\OrderStatusPushService;
use App\Modules\Orders\Actions\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\CloudMessage;
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

test('status changes including client cancellation publish a push event', function () {
    Event::fake([OrderStatusChanged::class]);
    [$client, $order] = orderForPushTests(OrderStatus::Processing);

    app(OrderWorkflow::class)->confirm($order);

    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event): bool => $event->orderId === $order->id && $event->status === OrderStatus::Confirmed);
    Event::fake([OrderStatusChanged::class]);
    app(OrderWorkflow::class)->cancel($order->refresh(), $client);
    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event): bool => $event->orderId === $order->id && $event->status === OrderStatus::Cancelled);
});

test('order workflow publishes every server-driven status transition', function () {
    Event::fake([OrderStatusChanged::class]);
    [$client, $order] = orderForPushTests(OrderStatus::Processing);
    $firstCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $secondCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $order->service->forceFill(['required_cleaners' => 2])->save();
    $workflow = app(OrderWorkflow::class);

    $workflow->confirm($order);
    $workflow->accept($order->refresh(), $firstCleaner);
    $workflow->accept($order->refresh(), $secondCleaner);
    $workflow->start($order->refresh(), $firstCleaner);
    $workflow->complete($order->refresh(), $firstCleaner);

    foreach ([
        OrderStatus::Confirmed,
        OrderStatus::TeamFormed,
        OrderStatus::InProgress,
        OrderStatus::AwaitingPayment,
    ] as $status) {
        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $event): bool => $event->orderId === $order->id && $event->status === $status,
        );
    }
});

test('the status push includes the public order id only in data and a friendly status message', function (OrderStatus $status, string $message) {
    [$client, $order] = orderForPushTests($status);
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

    app(SendOrderStatusPush::class)->handle(new OrderStatusChanged($order->id, $status));

    expect($gateway->sent)->toBe([
        'token' => 'token',
        'title' => 'Клиномания',
        'body' => $message,
        'data' => ['order_id' => $order->public_id],
    ]);
})->with([
    'confirmed' => [OrderStatus::Confirmed, 'Ваша заявка на уборку подтверждена!'],
    'team formed' => [OrderStatus::TeamFormed, 'Команда клинеров для вашей уборки сформирована!'],
    'in progress' => [OrderStatus::InProgress, 'Клинеры приступили к вашей уборке.'],
    'awaiting payment' => [OrderStatus::AwaitingPayment, 'Уборка завершена! Осталось оплатить заказ.'],
    'completed' => [OrderStatus::Completed, 'Спасибо! Оплата получена, заявка завершена.'],
    'cancelled' => [OrderStatus::Cancelled, 'Ваша заявка на уборку отменена.'],
]);

test('status push respects client preferences and token availability', function () {
    [$client, $order] = orderForPushTests(OrderStatus::Confirmed);
    $client->clientProfile()->create(['push_notifications_enabled' => false, 'fcm_token' => 'token']);
    $gateway = new class implements PushGateway
    {
        public int $sent = 0;

        public function send(string $token, string $title, string $body, array $data): void
        {
            $this->sent++;
        }
    };
    app()->instance(PushGateway::class, $gateway);

    app(OrderStatusPushService::class)->send($order->id, OrderStatus::Confirmed);
    expect($gateway->sent)->toBe(0);

    $client->clientProfile()->update(['push_notifications_enabled' => true, 'fcm_token' => null]);
    app(OrderStatusPushService::class)->send($order->id, OrderStatus::Confirmed);
    expect($gateway->sent)->toBe(0);
});

test('an invalid Firebase token is removed from the client profile', function () {
    [$client, $order] = orderForPushTests(OrderStatus::Confirmed);
    $client->clientProfile()->create(['push_notifications_enabled' => true, 'fcm_token' => 'invalid-token']);
    app()->instance(PushGateway::class, new class implements PushGateway
    {
        public function send(string $token, string $title, string $body, array $data): void
        {
            throw NotFound::becauseTokenNotFound($token);
        }
    });

    app(OrderStatusPushService::class)->send($order->id, OrderStatus::Confirmed);

    expect($client->refresh()->clientProfile->fcm_token)->toBeNull();
});

test('a temporary Firebase failure is rethrown for queue retry', function () {
    [$client, $order] = orderForPushTests(OrderStatus::Confirmed);
    $client->clientProfile()->create(['push_notifications_enabled' => true, 'fcm_token' => 'token']);
    app()->instance(PushGateway::class, new class implements PushGateway
    {
        public function send(string $token, string $title, string $body, array $data): void
        {
            throw new ServerUnavailable('Firebase is temporarily unavailable.');
        }
    });

    app(OrderStatusPushService::class)->send($order->id, OrderStatus::Confirmed);
})->throws(ServerUnavailable::class);

test('Firebase gateway builds an FCM notification and data message', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')
        ->once()
        ->withArgs(function (CloudMessage $message, bool $validateOnly = false): bool {
            return $validateOnly === false && $message->jsonSerialize() === [
                'data' => ['order_id' => '01J2QM1R7H7YV9JH1KACD6ZK3R'],
                'notification' => [
                    'title' => 'Статус заявки изменён',
                    'body' => 'Заявка изменена',
                ],
                'token' => 'device-token',
            ];
        })
        ->andReturn(['name' => 'projects/test/messages/1']);

    (new FirebasePushGateway($messaging))->send(
        'device-token',
        'Статус заявки изменён',
        'Заявка изменена',
        ['order_id' => '01J2QM1R7H7YV9JH1KACD6ZK3R'],
    );
});

test('disabled preferences are checked again before a queued push is sent', function () {
    [$client, $order] = orderForPushTests(OrderStatus::Confirmed);
    $client->clientProfile()->create(['push_notifications_enabled' => true, 'fcm_token' => 'token']);
    $gateway = new class implements PushGateway
    {
        public bool $sent = false;

        public function send(string $token, string $title, string $body, array $data): void
        {
            $this->sent = true;
        }
    };
    app()->instance(PushGateway::class, $gateway);

    $client->clientProfile()->update(['push_notifications_enabled' => false]);
    app(SendOrderStatusPush::class)->handle(new OrderStatusChanged($order->id, OrderStatus::Confirmed));
    expect($gateway->sent)->toBeFalse();
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
