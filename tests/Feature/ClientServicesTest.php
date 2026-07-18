<?php

use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('client can browse a service and create an order directly', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    $service = CleaningService::query()->create([
        'name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'min_price' => 7700,
        'min_area' => 30, 'max_area' => 160, 'area_step' => 10, 'cleaners_label' => '1 клинер', 'duration_label' => '2–3 часа',
    ]);
    foreach ([['room-1', 'room', false, true, 0], ['support', 'cleaning', false, true, 0], ['fridge-inside', 'extra', true, false, 800]] as [$code, $group, $addon, $default, $modifier]) {
        ServiceOption::query()->create(['cleaning_service_id' => $service->id, 'code' => $code, 'group' => $group, 'title' => $code, 'is_addon' => $addon, 'is_default' => $default, 'price_modifier' => $modifier]);
    }

    $this->getJson('/api/v1/client/services/standard')->assertOk()->assertJsonPath('data.id', 'standard');
    $payload = [
        'service_id' => 'standard',
        'room_option_id' => 'room-1',
        'cleaning_option_id' => 'support',
        'extra_option_ids' => ['fridge-inside'],
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'address' => [
            'full_address' => '  Иркутск, ул. Ленина, 10  ',
            'entrance' => ' ',
            'comment' => ' Позвонить за 15 минут ',
        ],
    ];

    $this->withHeader('Idempotency-Key', '550e8400-e29b-41d4-a716-446655440000')
        ->postJson('/api/v1/client/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.total_price', 8500)
        ->assertJsonPath('data.currency', 'RUB')
        ->assertJsonPath('data.service.id', 'standard');

    $order = CleaningOrder::query()->sole();
    expect($order->address)->toBe('Иркутск, ул. Ленина, 10')
        ->and($order->addressSnapshot->entrance)->toBeNull()
        ->and($order->addressSnapshot->comment)->toBe('Позвонить за 15 минут')
        ->and($order->lineItems)->toHaveCount(4);
});

test('replaying an identical order request is idempotent', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    $service = CleaningService::query()->create(['name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'min_price' => 7700]);
    ServiceOption::query()->create(['cleaning_service_id' => $service->id, 'code' => 'fridge-inside', 'group' => 'extra', 'title' => 'Холодильник', 'is_addon' => true, 'price_modifier' => 800]);
    $payload = ['service_id' => 'standard', 'extra_option_ids' => ['fridge-inside'], 'scheduled_at' => now()->addDay()->toIso8601String(), 'address' => ['full_address' => 'Иркутск, Ленина, 10']];
    $key = '550e8400-e29b-41d4-a716-446655440001';

    $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/client/orders', $payload)->assertCreated();
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/client/orders', $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(CleaningOrder::query()->count())->toBe(1);
});

test('order accepts an ISO date-time without an explicit offset as UTC', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    CleaningService::query()->create(['name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'min_price' => 7700]);

    $this->withHeader('Idempotency-Key', '550e8400-e29b-41d4-a716-446655440004')
        ->postJson('/api/v1/client/orders', [
            'service_id' => 'standard',
            'extra_option_ids' => [],
            'scheduled_at' => now()->addDay()->format('Y-m-d\\TH:i:s.v'),
            'address' => ['full_address' => 'Кировская область, деревня Москва, 5'],
            'comment' => 'Тест',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'processing');

    $this->assertDatabaseHas('order_addresses', ['comment' => 'Тест']);
});

test('order rejects a reused idempotency key with a different body', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    Sanctum::actingAs($client);
    $service = CleaningService::query()->create(['name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'min_price' => 7700]);
    $payload = ['service_id' => 'standard', 'extra_option_ids' => [], 'scheduled_at' => now()->addDay()->toIso8601String(), 'address' => ['full_address' => 'Иркутск, Ленина, 10']];
    $key = '550e8400-e29b-41d4-a716-446655440002';

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/client/orders', $payload)->assertCreated();
    $payload['address']['full_address'] = 'Иркутск, Карла Маркса, 1';

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/client/orders', $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_key_conflict');
});

test('order requires a client role', function () {
    $cleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    Sanctum::actingAs($cleaner);

    $this->withHeader('Idempotency-Key', '550e8400-e29b-41d4-a716-446655440003')
        ->postJson('/api/v1/client/orders', [])
        ->assertForbidden();
});
