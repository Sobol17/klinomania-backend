<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('client can update editable profile fields', function () {
    $client = User::factory()->create([
        'name' => null,
        'email' => null,
        'phone' => '+79990000101',
        'role' => UserRole::Client,
    ]);

    $client->clientProfile()->create([
        'name' => null,
        'address' => null,
        'push_notifications_enabled' => false,
        'email_marketing_enabled' => false,
    ]);

    Sanctum::actingAs($client);

    $this->patchJson('/api/v1/client/profile', [
        'name' => 'Иван',
        'email' => 'ivan@example.com',
        'address' => 'Москва, ул. Тверская, 1',
        'push_notifications_enabled' => true,
        'email_marketing_enabled' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Иван')
        ->assertJsonPath('data.email', 'ivan@example.com')
        ->assertJsonPath('data.phone', '+79990000101')
        ->assertJsonPath('data.client_profile.name', 'Иван')
        ->assertJsonPath('data.client_profile.address', 'Москва, ул. Тверская, 1')
        ->assertJsonPath('data.client_profile.push_notifications_enabled', true)
        ->assertJsonPath('data.client_profile.email_marketing_enabled', false);

    $client->refresh();

    expect($client->name)->toBe('Иван')
        ->and($client->email)->toBe('ivan@example.com')
        ->and($client->clientProfile->address)->toBe('Москва, ул. Тверская, 1')
        ->and($client->clientProfile->push_notifications_enabled)->toBeTrue()
        ->and($client->clientProfile->email_marketing_enabled)->toBeFalse();
});

test('client profile update does not change phone', function () {
    $client = User::factory()->create([
        'phone' => '+79990000102',
        'role' => UserRole::Client,
    ]);

    Sanctum::actingAs($client);

    $this->patchJson('/api/v1/client/profile', [
        'name' => 'Иван',
        'email' => 'ivan2@example.com',
        'phone' => '+79990000999',
        'address' => 'Москва',
        'push_notifications_enabled' => true,
        'email_marketing_enabled' => true,
    ])->assertOk()
        ->assertJsonPath('data.phone', '+79990000102');

    expect($client->refresh()->phone)->toBe('+79990000102');
});

test('client can partially update profile fields', function () {
    $client = User::factory()->create([
        'name' => 'Старое имя',
        'email' => 'old@example.com',
        'phone' => '+79990000107',
        'role' => UserRole::Client,
    ]);

    $client->clientProfile()->create([
        'name' => 'Старое имя',
        'address' => 'Старый адрес',
        'push_notifications_enabled' => true,
        'email_marketing_enabled' => true,
    ]);

    Sanctum::actingAs($client);

    $this->patchJson('/api/v1/client/profile', [
        'address' => 'Новый адрес',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Старое имя')
        ->assertJsonPath('data.email', 'old@example.com')
        ->assertJsonPath('data.client_profile.address', 'Новый адрес')
        ->assertJsonPath('data.client_profile.push_notifications_enabled', true)
        ->assertJsonPath('data.client_profile.email_marketing_enabled', true);

    $client->refresh();

    expect($client->name)->toBe('Старое имя')
        ->and($client->email)->toBe('old@example.com')
        ->and($client->clientProfile->address)->toBe('Новый адрес')
        ->and($client->clientProfile->push_notifications_enabled)->toBeTrue()
        ->and($client->clientProfile->email_marketing_enabled)->toBeTrue();
});

test('cleaner cannot update client profile', function () {
    $cleaner = User::factory()->create([
        'phone' => '+79990000103',
        'role' => UserRole::Cleaner,
    ]);

    Sanctum::actingAs($cleaner);

    $this->patchJson('/api/v1/client/profile', [
        'name' => 'Иван',
        'email' => 'ivan3@example.com',
        'address' => 'Москва',
        'push_notifications_enabled' => true,
        'email_marketing_enabled' => true,
    ])->assertForbidden();
});

test('client profile update rejects duplicate email', function () {
    User::factory()->create([
        'email' => 'busy@example.com',
        'phone' => '+79990000104',
        'role' => UserRole::Client,
    ]);

    $client = User::factory()->create([
        'email' => 'free@example.com',
        'phone' => '+79990000105',
        'role' => UserRole::Client,
    ]);

    Sanctum::actingAs($client);

    $this->patchJson('/api/v1/client/profile', [
        'name' => 'Иван',
        'email' => 'busy@example.com',
        'address' => 'Москва',
        'push_notifications_enabled' => true,
        'email_marketing_enabled' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('client profile update creates missing client profile', function () {
    $client = User::factory()->create([
        'phone' => '+79990000106',
        'role' => UserRole::Client,
    ]);

    Sanctum::actingAs($client);

    $this->patchJson('/api/v1/client/profile', [
        'name' => 'Мария',
        'email' => 'maria@example.com',
        'address' => 'Санкт-Петербург',
        'push_notifications_enabled' => false,
        'email_marketing_enabled' => true,
    ])->assertOk()
        ->assertJsonPath('data.client_profile.name', 'Мария')
        ->assertJsonPath('data.client_profile.address', 'Санкт-Петербург')
        ->assertJsonPath('data.client_profile.email_marketing_enabled', true);

    expect($client->refresh()->clientProfile)->not->toBeNull();
});
