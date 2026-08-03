<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Notifications\Events\OrderCreated;
use App\Modules\Notifications\Listeners\NotifyAdminsAboutNewOrder;
use App\Modules\Notifications\Notifications\NewOrderCreatedNotification;
use App\Modules\Orders\Actions\CreateOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('creating an order publishes one event while an idempotent replay does not', function () {
    Event::fake([OrderCreated::class]);
    $client = User::factory()->create(['role' => UserRole::Client]);
    CleaningService::query()->create([
        'name' => 'Поддерживающая уборка',
        'slug' => 'standard',
        'base_price' => 7700,
        'min_price' => 7700,
    ]);
    $input = [
        'service_id' => 'standard',
        'extra_option_ids' => [],
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'address' => ['full_address' => 'Иркутск, ул. Ленина, 10'],
    ];
    $key = '550e8400-e29b-41d4-a716-446655440010';
    $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

    $first = app(CreateOrder::class)->execute($client, $key, $hash, $input);
    $replayed = app(CreateOrder::class)->execute($client, $key, $hash, $input);

    expect($replayed->is($first))->toBeTrue();
    Event::assertDispatchedTimes(OrderCreated::class, 1);
    Event::assertDispatched(
        OrderCreated::class,
        fn (OrderCreated $event): bool => $event->orderId === $first->id,
    );
});

test('the new order listener notifies every administrator with an email', function () {
    Notification::fake();
    $firstAdmin = User::factory()->create(['role' => UserRole::Admin]);
    $secondAdmin = User::factory()->create(['role' => UserRole::Admin]);
    $adminWithoutEmail = User::factory()->create([
        'role' => UserRole::Admin,
        'email' => null,
    ]);
    $client = User::factory()->create(['role' => UserRole::Client]);

    app(NotifyAdminsAboutNewOrder::class)->handle(new OrderCreated(123));

    Notification::assertSentTo(
        [$firstAdmin, $secondAdmin],
        NewOrderCreatedNotification::class,
        fn (NewOrderCreatedNotification $notification): bool => $notification->orderId === 123,
    );
    Notification::assertNotSentTo($adminWithoutEmail, NewOrderCreatedNotification::class);
    Notification::assertNotSentTo($client, NewOrderCreatedNotification::class);
});

test('the new order email contains order details and an admin link', function () {
    config(['app.url' => 'https://api.klinomania.test']);
    URL::forceRootUrl('https://api.klinomania.test');
    URL::forceScheme('https');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = User::factory()->create([
        'role' => UserRole::Client,
        'name' => null,
        'phone' => '+79990001122',
    ]);
    $client->clientProfile()->create(['name' => 'Анна']);
    $service = CleaningService::query()->create([
        'name' => 'Поддерживающая уборка',
        'slug' => 'standard',
        'base_price' => 8500,
    ]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3R',
        'client_id' => $client->id,
        'cleaning_service_id' => $service->id,
        'status' => OrderStatus::Processing,
        'address' => 'Иркутск, ул. Ленина, 10',
        'scheduled_at' => '2026-08-01 10:30:00',
        'comment' => 'Позвонить за 15 минут',
        'total_price' => 8500,
    ]);
    $notification = new NewOrderCreatedNotification($order->id);

    $mail = $notification->toMail($admin);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->tries)->toBe(3)
        ->and($notification->backoff)->toBe([60, 300])
        ->and($mail->subject)->toBe('Новая заявка №01J2QM1R7H7YV9JH1KACD6ZK3R')
        ->and($mail->introLines)->toContain(
            '**ID заявки:** 01J2QM1R7H7YV9JH1KACD6ZK3R',
            '**Статус:** В обработке',
            '**Клиент:** Анна',
            '**Телефон:** +79990001122',
            '**Услуга:** Поддерживающая уборка',
            '**Дата и время:** 01.08.2026 10:30 (UTC)',
            '**Адрес:** Иркутск, ул. Ленина, 10',
            '**Комментарий:** Позвонить за 15 минут',
            '**Итоговая сумма:** 8 500 ₽',
        )
        ->and($mail->actionText)->toBe('Открыть заявку')
        ->and($mail->actionUrl)->toBe(
            'https://api.klinomania.test/admin/cleaning-orders/01J2QM1R7H7YV9JH1KACD6ZK3R/edit',
        );
});
