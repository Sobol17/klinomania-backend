<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\Complaint;
use App\Models\User;
use App\Modules\Complaints\Events\ComplaintCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function apiComplaintOrder(User $client, OrderStatus $status = OrderStatus::Completed): CleaningOrder
{
    $service = CleaningService::query()->create([
        'name' => 'Уборка',
        'slug' => 'service-'.Str::lower(Str::random(8)),
        'base_price' => 1000,
    ]);

    return CleaningOrder::query()->create([
        'public_id' => (string) Str::ulid(),
        'client_id' => $client->id,
        'cleaning_service_id' => $service->id,
        'status' => $status,
        'address' => 'Иркутск',
        'scheduled_at' => now(),
        'total_price' => 1000,
    ]);
}

test('client submits a complaint for own order', function () {
    Event::fake([ComplaintCreated::class]);
    $client = User::factory()->create(['role' => UserRole::Client]);
    $order = apiComplaintOrder($client);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/orders/{$order->public_id}/complaints", [
        'subject' => 'Повреждение',
        'message' => 'Повреждён стол',
    ])->assertCreated()
        ->assertJsonPath('data.order_id', $order->public_id)
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.subject', 'Повреждение');

    $this->assertDatabaseHas('complaints', [
        'cleaning_order_id' => $order->id,
        'client_id' => $client->id,
        'status' => 'new',
    ]);
    Event::assertDispatched(ComplaintCreated::class);
});

test('client cannot submit a complaint for another clients order', function () {
    $owner = User::factory()->create(['role' => UserRole::Client]);
    $other = User::factory()->create(['role' => UserRole::Client]);
    $order = apiComplaintOrder($owner);
    Sanctum::actingAs($other);

    $this->postJson("/api/v1/client/orders/{$order->public_id}/complaints", [
        'subject' => 'Тема', 'message' => 'Текст',
    ])->assertForbidden();
    $this->postJson('/api/v1/client/orders/unknown/complaints', [
        'subject' => 'Тема', 'message' => 'Текст',
    ])->assertNotFound();
});

test('complaint fields are validated', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $order = apiComplaintOrder($client);
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/client/orders/{$order->public_id}/complaints", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'message']);
});

test('client sees only own complaints newest first', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $other = User::factory()->create(['role' => UserRole::Client]);
    $order = apiComplaintOrder($client);
    Complaint::factory()->create(['client_id' => $client->id, 'cleaning_order_id' => $order->id, 'subject' => 'Первая', 'created_at' => now()->subMinute()]);
    Complaint::factory()->create(['client_id' => $client->id, 'cleaning_order_id' => $order->id, 'subject' => 'Вторая']);
    Complaint::factory()->create(['client_id' => $other->id, 'subject' => 'Чужая']);
    Sanctum::actingAs($client);

    $this->getJson('/api/v1/client/complaints')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.subject', 'Вторая')
        ->assertJsonMissing(['subject' => 'Чужая']);
});

test('complaint submission is rate limited', function () {
    Event::fake([ComplaintCreated::class]);
    $client = User::factory()->create(['role' => UserRole::Client]);
    $order = apiComplaintOrder($client);
    Sanctum::actingAs($client);
    $url = "/api/v1/client/orders/{$order->public_id}/complaints";

    foreach (range(1, 5) as $attempt) {
        $this->postJson($url, ['subject' => "Тема {$attempt}", 'message' => 'Текст'])->assertCreated();
    }

    $this->postJson($url, ['subject' => 'Лишняя', 'message' => 'Текст'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});
