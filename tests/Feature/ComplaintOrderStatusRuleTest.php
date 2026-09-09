<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Complaints\Events\ComplaintCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('complaint availability follows order status', function (OrderStatus $status, bool $allowed) {
    Event::fake([ComplaintCreated::class]);
    $client = User::factory()->create(['role' => UserRole::Client]);
    $service = CleaningService::query()->create(['name' => 'Уборка', 'slug' => 'status-test', 'base_price' => 1000]);
    $order = CleaningOrder::query()->create([
        'public_id' => (string) Str::ulid(), 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => $status,
        'address' => 'Иркутск', 'scheduled_at' => now(), 'total_price' => 1000,
    ]);
    Sanctum::actingAs($client);

    $response = $this->postJson("/api/v1/client/orders/{$order->public_id}/complaints", [
        'subject' => 'Тема', 'message' => 'Сообщение',
    ]);

    if ($allowed) {
        $response->assertCreated();
    } else {
        $response->assertConflict()->assertJsonPath('code', 'complaint_not_allowed');
    }
})->with([
    [OrderStatus::Processing, false],
    [OrderStatus::Confirmed, false],
    [OrderStatus::TeamFormed, false],
    [OrderStatus::InProgress, true],
    [OrderStatus::AwaitingPayment, true],
    [OrderStatus::Completed, true],
    [OrderStatus::Cancelled, false],
]);
