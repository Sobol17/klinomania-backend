<?php

use App\Enums\UserRole;
use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('client can browse a service and create a server-side quote', function () {
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
    $this->postJson('/api/v1/client/service-quotes', ['service_id' => 'standard', 'area_sqm' => 60, 'room_option_id' => 'room-1', 'cleaning_option_id' => 'support', 'extra_option_ids' => ['fridge-inside']])
        ->assertOk()->assertJsonPath('data.total_price', 8500)->assertJsonPath('data.service_id', 'standard');
});
