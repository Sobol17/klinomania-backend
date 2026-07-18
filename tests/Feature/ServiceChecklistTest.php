<?php

use App\Enums\ChecklistZone;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceChecklistItem;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('client receives a service checklist in its detail response', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create([
        'name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'min_price' => 7700,
    ]);
    $service->checklistItems()->createMany([
        ['zone' => ChecklistZone::Rooms, 'title' => 'Протираем пыль', 'sort_order' => 20],
        ['zone' => ChecklistZone::Rooms, 'title' => 'Моем пол', 'sort_order' => 10],
    ]);

    Sanctum::actingAs($client);

    $this->getJson('/api/v1/client/services/standard')
        ->assertOk()
        ->assertJsonPath('data.checklist.0.title', 'Моем пол')
        ->assertJsonPath('data.checklist.0.zone', 'rooms')
        ->assertJsonPath('data.checklist_sections.1.title', 'Комнаты, гардеробная и прихожая')
        ->assertJsonPath('data.checklist_sections.1.items.1.title', 'Протираем пыль');
});

test('assigned cleaners share an order checklist and must complete it before completing the order', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $firstCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $secondCleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $outsider = User::factory()->create(['role' => UserRole::Cleaner]);
    $service = CleaningService::query()->create([
        'name' => 'Базовый минимум', 'slug' => 'standard', 'base_price' => 7700, 'required_cleaners' => 2,
    ]);
    $firstItem = ServiceChecklistItem::query()->create([
        'cleaning_service_id' => $service->id, 'title' => 'Протираем пыль', 'sort_order' => 10,
    ]);
    $secondItem = ServiceChecklistItem::query()->create([
        'cleaning_service_id' => $service->id, 'title' => 'Моем пол', 'sort_order' => 20,
    ]);
    $order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK4A', 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::InProgress,
        'address' => 'Иркутск, Ленина, 10', 'scheduled_at' => now()->addDay(), 'total_price' => 7700,
    ]);
    $order->cleaners()->attach($firstCleaner->id, ['accepted_at' => now(), 'started_at' => now()]);
    $order->cleaners()->attach($secondCleaner->id, ['accepted_at' => now(), 'started_at' => now()]);
    ServiceOption::query()->create([
        'cleaning_service_id' => $service->id,
        'code' => 'fridge-inside',
        'group' => 'extra',
        'checklist_zone' => ChecklistZone::Kitchen,
        'title' => 'Холодильник внутри',
        'is_addon' => true,
    ]);
    $extraLineItem = $order->lineItems()->create([
        'kind' => 'extra_option',
        'source_option_id' => 'fridge-inside',
        'title' => 'Холодильник внутри',
        'amount' => 800,
    ]);

    Sanctum::actingAs($outsider);
    $this->patchJson("/api/v1/cleaner/orders/{$order->public_id}/checklist/{$firstItem->id}", ['completed' => true])
        ->assertForbidden();

    Sanctum::actingAs($firstCleaner);
    $this->getJson("/api/v1/cleaner/orders/{$order->public_id}")
        ->assertOk()
        ->assertJsonPath('data.checklist.0.completed', false)
        ->assertJsonPath('data.checklist.2.kind', 'extra_service')
        ->assertJsonPath('data.checklist.2.zone', 'kitchen')
        ->assertJsonPath('data.checklist.2.title', 'Холодильник внутри');
    $this->patchJson("/api/v1/cleaner/orders/{$order->public_id}/checklist/{$firstItem->id}", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.completed', true);
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/complete")
        ->assertConflict()
        ->assertJsonPath('code', 'checklist_incomplete');

    Sanctum::actingAs($secondCleaner);
    $this->getJson("/api/v1/cleaner/orders/{$order->public_id}")
        ->assertOk()
        ->assertJsonPath('data.checklist.0.completed', true);
    $this->patchJson("/api/v1/cleaner/orders/{$order->public_id}/checklist/{$secondItem->id}", ['completed' => true])
        ->assertOk();
    $this->patchJson("/api/v1/cleaner/orders/{$order->public_id}/checklist/extra-{$extraLineItem->id}", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.kind', 'extra_service')
        ->assertJsonPath('data.completed', true);
    $this->postJson("/api/v1/cleaner/orders/{$order->public_id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_payment');
});
