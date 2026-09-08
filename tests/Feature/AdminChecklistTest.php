<?php

use App\Enums\ChecklistZone;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Filament\Resources\CleaningOrders\Pages\ViewCleaningOrder;
use App\Filament\Resources\CleaningOrders\Support\OrderChecklistView;
use App\Filament\Resources\CleaningServices\Pages\EditCleaningService;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->client = User::factory()->create(['role' => UserRole::Client]);
    $this->cleaner = User::factory()->create([
        'role' => UserRole::Cleaner,
        'name' => 'Наталья Бердникова',
    ]);
    $this->service = CleaningService::query()->create([
        'name' => 'Генеральная уборка',
        'slug' => 'general-checklist',
        'base_price' => 9000,
    ]);
    [$this->roomItem, $this->kitchenItem] = $this->service->checklistItems()->createMany([
        ['zone' => ChecklistZone::Rooms, 'title' => 'Протереть пыль', 'sort_order' => 10],
        ['zone' => ChecklistZone::Kitchen, 'title' => 'Вымыть рабочие поверхности', 'sort_order' => 20],
    ]);
    $this->order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZN01',
        'client_id' => $this->client->id,
        'cleaning_service_id' => $this->service->id,
        'status' => OrderStatus::InProgress,
        'address' => 'Иркутск, Ленина, 1',
        'scheduled_at' => now()->addHour(),
        'total_price' => 9800,
    ]);
    $this->order->checklistItems()->create([
        'service_checklist_item_id' => $this->roomItem->id,
        'completed_at' => now(),
        'completed_by' => $this->cleaner->id,
    ]);
    $this->extraLineItem = $this->order->lineItems()->create([
        'kind' => 'extra_option',
        'source_option_id' => 'fridge-inside',
        'title' => 'Вымыть холодильник внутри',
        'amount' => 800,
    ]);
    $this->order->extraChecklistItems()->create([
        'order_line_item_id' => $this->extraLineItem->id,
        'completed_at' => now(),
        'completed_by' => $this->cleaner->id,
    ]);
});

test('order checklist UI groups zones extras and shared progress', function () {
    expect(OrderChecklistView::metrics($this->order))->toMatchArray([
        'completed' => 2,
        'total' => 3,
        'remaining' => 1,
        'percent' => 67,
    ]);

    Livewire::test(ViewCleaningOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertOk()
        ->assertSee('Выполнено 2 из 3')
        ->assertSee('67%')
        ->assertSee('Ход выполнения')
        ->assertSee(ChecklistZone::Rooms->getLabel())
        ->assertSee(ChecklistZone::Kitchen->getLabel())
        ->assertSee('Дополнительные работы')
        ->assertSee('Вымыть холодильник внутри')
        ->assertSee('Наталья Бердникова · Клинер')
        ->assertSee('Ожидает выполнения');

    Livewire::test(ListCleaningOrders::class)
        ->assertCanSeeTableRecords([$this->order])
        ->assertSee('2 из 3');
});

test('service editor explains live template behavior and shows zone navigation', function () {
    Livewire::test(EditCleaningService::class, ['record' => $this->service->id])
        ->assertOk()
        ->assertSee('Маршрут уборки')
        ->assertSee('Изменения применяются к текущим заказам')
        ->assertSee(ChecklistZone::Everywhere->getLabel())
        ->assertSee(ChecklistZone::Rooms->getLabel())
        ->assertSee(ChecklistZone::Kitchen->getLabel())
        ->assertSee(ChecklistZone::Bathroom->getLabel());
});

test('current checklist template remains live for an unfinished order', function () {
    $this->service->checklistItems()->create([
        'zone' => ChecklistZone::Bathroom,
        'title' => 'Продезинфицировать сантехнику',
        'sort_order' => 30,
    ]);

    expect(OrderChecklistView::metrics($this->order->fresh()))->toMatchArray([
        'completed' => 2,
        'total' => 4,
        'remaining' => 2,
        'percent' => 50,
    ]);
});
