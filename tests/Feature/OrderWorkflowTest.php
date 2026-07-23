<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Orders\Actions\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('a moderator confirms an order and cleaners form and complete its team', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $firstCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $secondCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $service = CleaningService::query()->create([
        'name' => 'Генеральская', 'slug' => 'premium', 'base_price' => 11900, 'required_cleaners' => 2,
    ]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3R', 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::Processing,
        'address' => 'Иркутск, Ленина, 10', 'scheduled_at' => now()->addDay(), 'total_price' => 11900,
    ]);

    app(OrderWorkflow::class)->confirm($order);
    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);

    Sanctum::actingAs($firstCleaner);
    $this->getJson('/api/v1/cleaner/orders/available')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/cleaner/orders/{$order->public_id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/accept")
        ->assertOk()->assertJsonPath('data.status', 'confirmed');

    Sanctum::actingAs($secondCleaner);
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/accept")
        ->assertOk()->assertJsonPath('data.status', 'team_formed');
    expect($order->refresh()->cleaners)->toHaveCount(2);
    $order->lineItems()->createMany([
        ['kind' => 'base', 'title' => 'Генеральская', 'amount' => 11900],
        ['kind' => 'extra_option', 'source_option_id' => 'fridge-inside', 'title' => 'Холодильник внутри', 'amount' => 800],
    ]);
    $this->getJson("/api/v1/cleaner/orders/{$order->public_id}")
        ->assertOk()
        ->assertJsonPath('data.extra_options.0.option_id', 'fridge-inside')
        ->assertJsonPath('data.extra_options.0.title', 'Холодильник внутри');
    $this->getJson('/api/v1/cleaner/orders')->assertOk()->assertJsonCount(1, 'data');
    $extraLineItem = $order->lineItems()->where('kind', 'extra_option')->sole();
    $this->patchJson("/api/v1/cleaner/orders/{$order->public_id}/checklist/extra-{$extraLineItem->id}", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.kind', 'extra_service');

    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/start")
        ->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/complete")
        ->assertOk()->assertJsonPath('data.status', 'awaiting_payment');
});

test('client can cancel only their unformed order', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create(['name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3S', 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::Processing,
        'address' => 'Иркутск, Ленина, 10', 'scheduled_at' => now()->addDay(), 'total_price' => 7700,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/client/orders/{$order->public_id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    $order->forceFill(['status' => OrderStatus::TeamFormed])->save();
    $this->postJson("/api/v1/client/orders/{$order->public_id}/cancel")
        ->assertConflict()->assertJsonPath('code', 'invalid_order_transition');
});

test('client can view only their order details by public id', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $otherClient = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create([
        'name' => 'Базовый минимум',
        'slug' => 'standard',
        'base_price' => 7700,
    ]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3U',
        'client_id' => $client->id,
        'cleaning_service_id' => $service->id,
        'status' => OrderStatus::Processing,
        'address' => 'Иркутск, Ленина, 10',
        'scheduled_at' => now()->addDay(),
        'total_price' => 8500,
    ]);
    $order->addressSnapshot()->create([
        'full_address' => 'Иркутск, Ленина, 10',
        'apartment' => '5',
    ]);
    $order->lineItems()->createMany([
        ['kind' => 'base', 'title' => 'Базовый минимум', 'amount' => 7700],
        ['kind' => 'extra_option', 'source_option_id' => 'fridge-inside', 'title' => 'Холодильник внутри', 'amount' => 800],
    ]);

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/client/orders/{$order->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->public_id)
        ->assertJsonPath('data.status_label', 'В обработке')
        ->assertJsonPath('data.service.id', 'standard')
        ->assertJsonPath('data.address.apartment', '5')
        ->assertJsonPath('data.line_items.1.option_id', 'fridge-inside')
        ->assertJsonMissingPath('data.line_items.1.cleaner_earnings')
        ->assertJsonMissingPath('data.client_id');

    Sanctum::actingAs($otherClient);
    $this->getJson("/api/v1/client/orders/{$order->public_id}")->assertForbidden();
    $this->getJson('/api/v1/client/orders/01J2QM1R7H7YV9JH1KACD6ZK3V')->assertNotFound();
});

test('a single cleaner starts an order when joining its one-person team', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $cleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $service = CleaningService::query()->create(['name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'required_cleaners' => 1]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3T', 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::Confirmed,
        'address' => 'Иркутск, Ленина, 10', 'scheduled_at' => now()->addDay(), 'total_price' => 7700,
    ]);

    Sanctum::actingAs($cleaner);
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/accept")
        ->assertOk()->assertJsonPath('data.status', 'in_progress');

    expect($order->refresh()->status)->toBe(OrderStatus::InProgress)
        ->and($order->cleaners()->first()->pivot->started_at)->not->toBeNull();
});
