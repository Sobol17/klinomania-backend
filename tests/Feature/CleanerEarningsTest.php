<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('checkout snapshots base and extra cleaner earnings and keeps them after catalog changes', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    $service = CleaningService::query()->create([
        'name' => 'Комплексная', 'slug' => 'complex', 'base_price' => 9000, 'min_price' => 9000,
        'cleaner_base_earnings' => 5000,
    ]);
    foreach ([
        ['rooms', 'room', false, 0, 0],
        ['regular', 'cleaning', false, 0, 0],
        ['fridge', 'extra', true, 500, 20],
        ['windows', 'extra', true, 1000, 50],
    ] as [$code, $group, $addon, $price, $percent]) {
        ServiceOption::query()->create([
            'cleaning_service_id' => $service->id, 'code' => $code, 'group' => $group, 'title' => $code,
            'is_addon' => $addon, 'price_modifier' => $price, 'cleaner_revenue_percent' => $percent,
        ]);
    }

    $this->withHeader('Idempotency-Key', '9a45e77a-33f2-44fb-bf78-327f9fe71811')
        ->postJson('/api/v1/client/orders', [
            'service_id' => 'complex', 'room_option_id' => 'rooms', 'cleaning_option_id' => 'regular',
            'extra_option_ids' => ['fridge', 'windows'], 'scheduled_at' => now()->addDay()->toIso8601String(),
            'address' => ['full_address' => 'Иркутск, Ленина, 10'],
        ])->assertCreated();

    $order = CleaningOrder::query()->with('lineItems')->sole();
    expect($order->lineItems->pluck('cleaner_earnings')->all())->toBe([5000, 0, 0, 100, 500])
        ->and($order->lineItems->sum('cleaner_earnings'))->toBe(5600);

    $service->update(['cleaner_base_earnings' => 1]);
    ServiceOption::query()->where('code', 'fridge')->update(['cleaner_revenue_percent' => 99]);
    expect($order->fresh()->lineItems()->sum('cleaner_earnings'))->toBe(5600);
});

test('extra cleaner earnings are rounded mathematically to rubles', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    $service = CleaningService::query()->create(['name' => 'Тест', 'slug' => 'rounding', 'base_price' => 1000, 'min_price' => 1000]);
    ServiceOption::query()->create([
        'cleaning_service_id' => $service->id, 'code' => 'small-extra', 'group' => 'extra', 'title' => 'Допработка',
        'is_addon' => true, 'price_modifier' => 333, 'cleaner_revenue_percent' => 50,
    ]);

    $this->withHeader('Idempotency-Key', 'e743b966-29ed-44d2-a2d2-970e1a3c4ce3')
        ->postJson('/api/v1/client/orders', [
            'service_id' => 'rounding', 'extra_option_ids' => ['small-extra'], 'scheduled_at' => now()->addDay()->toIso8601String(),
            'address' => ['full_address' => 'Иркутск, Карла Маркса, 1'],
        ])->assertCreated();

    expect(CleaningOrder::query()->sole()->lineItems()->where('kind', 'extra_option')->value('cleaner_earnings'))->toBe(167);
});

test('cleaner API returns expected and assigned earnings shares', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $firstCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $secondCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $service = CleaningService::query()->create(['name' => 'Командная', 'slug' => 'team', 'base_price' => 10000, 'required_cleaners' => 3]);
    $availableOrder = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK31', 'client_id' => $client->id, 'cleaning_service_id' => $service->id,
        'status' => OrderStatus::Confirmed, 'address' => 'Иркутск, Ленина, 1', 'scheduled_at' => now()->addDay(), 'total_price' => 10000,
    ]);
    $availableOrder->lineItems()->create(['kind' => 'base', 'title' => 'Командная', 'amount' => 10000, 'cleaner_earnings' => 5600]);
    $availableOrder->cleaners()->attach($firstCleaner->id);

    Sanctum::actingAs($secondCleaner);
    $this->getJson('/api/v1/cleaner/orders/available')
        ->assertOk()->assertJsonPath('data.0.cleaner_earnings', 2800);

    $assignedOrder = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK32', 'client_id' => $client->id, 'cleaning_service_id' => $service->id,
        'status' => OrderStatus::TeamFormed, 'address' => 'Иркутск, Ленина, 2', 'scheduled_at' => now()->addDay(), 'total_price' => 10000,
    ]);
    $assignedOrder->lineItems()->create(['kind' => 'base', 'title' => 'Командная', 'amount' => 10000, 'cleaner_earnings' => 5601]);
    $assignedOrder->cleaners()->attach([$firstCleaner->id, $secondCleaner->id]);

    $this->getJson('/api/v1/cleaner/orders')
        ->assertOk()->assertJsonPath('data.0.cleaner_earnings', 2801);
    $this->getJson("/api/v1/cleaner/orders/{$assignedOrder->public_id}")
        ->assertOk()->assertJsonPath('data.cleaner_earnings', 2801);
});
