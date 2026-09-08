<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Filament\Resources\CleaningOrders\Pages\ViewCleaningOrder;
use App\Filament\Resources\CleaningOrders\RelationManagers\CleanersRelationManager;
use App\Filament\Resources\CleaningOrders\RelationManagers\PaymentAttemptsRelationManager;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Orders\Actions\AssignCleaner;
use App\Modules\Orders\Actions\OrderWorkflow;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->client = User::factory()->create([
        'role' => UserRole::Client,
        'name' => 'Анна Клиентова',
        'phone' => '+79990000001',
    ]);
    $this->service = CleaningService::query()->create([
        'name' => 'Командная уборка',
        'slug' => 'team-cleaning',
        'base_price' => 5000,
        'required_cleaners' => 2,
    ]);
    $this->order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZM01',
        'client_id' => $this->client->id,
        'cleaning_service_id' => $this->service->id,
        'status' => OrderStatus::Confirmed,
        'address' => 'Иркутск, Советская, 1',
        'scheduled_at' => now()->addDay(),
        'total_price' => 5000,
    ]);
});

test('admin assignment fills the team and dispatches a status event', function () {
    Event::fake([OrderStatusChanged::class]);
    $first = activeCleaner('Первый клинер', '+79990000002');
    $second = activeCleaner('Второй клинер', '+79990000003');
    $assigner = app(AssignCleaner::class);

    $assigner->assign($this->order, $first);

    expect($this->order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($this->order->cleaners()->find($first->id)->pivot->accepted_at)->not->toBeNull();
    Event::assertNotDispatched(OrderStatusChanged::class);

    $assigner->assign($this->order, $second);

    expect($this->order->refresh()->status)->toBe(OrderStatus::TeamFormed)
        ->and($this->order->cleaners)->toHaveCount(2);
    Event::assertDispatched(fn (OrderStatusChanged $event): bool => $event->orderId === $this->order->id
        && $event->status === OrderStatus::TeamFormed);
});

test('admin assignment rejects duplicates excess and inactive cleaners', function () {
    $first = activeCleaner('Первый клинер', '+79990000002');
    $second = activeCleaner('Второй клинер', '+79990000003');
    $third = activeCleaner('Третий клинер', '+79990000004');
    $inactive = activeCleaner('Неактивный клинер', '+79990000005', false);
    $assigner = app(AssignCleaner::class);

    $assigner->assign($this->order, $first);
    expect(fn () => $assigner->assign($this->order, $first))->toThrow(InvalidOrderTransition::class);
    expect(fn () => $assigner->assign($this->order, $inactive))->toThrow(InvalidOrderTransition::class);

    $assigner->assign($this->order, $second);
    expect(fn () => $assigner->assign($this->order, $third))->toThrow(InvalidOrderTransition::class);
    expect($this->order->cleaners()->count())->toBe(2);
});

test('removing an unstarted cleaner rolls an incomplete team back to confirmed', function () {
    Event::fake([OrderStatusChanged::class]);
    $first = activeCleaner('Первый клинер', '+79990000002');
    $second = activeCleaner('Второй клинер', '+79990000003');
    $this->order->cleaners()->attach([
        $first->id => ['accepted_at' => now()],
        $second->id => ['accepted_at' => now()],
    ]);
    $this->order->update(['status' => OrderStatus::TeamFormed]);

    app(AssignCleaner::class)->remove($this->order, $first);

    expect($this->order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and($this->order->cleaners()->whereKey($first->id)->exists())->toBeFalse();
    Event::assertDispatched(fn (OrderStatusChanged $event): bool => $event->status === OrderStatus::Confirmed);
});

test('a cleaner who started work cannot be removed', function () {
    $cleaner = activeCleaner('Первый клинер', '+79990000002');
    $this->order->cleaners()->attach($cleaner->id, ['accepted_at' => now(), 'started_at' => now()]);
    $this->order->update(['status' => OrderStatus::TeamFormed]);

    expect(fn () => app(AssignCleaner::class)->remove($this->order, $cleaner))
        ->toThrow(InvalidOrderTransition::class);
    expect($this->order->cleaners()->whereKey($cleaner->id)->exists())->toBeTrue();
});

test('admin can cancel an eligible order', function () {
    Event::fake([OrderStatusChanged::class]);
    $workflow = app(OrderWorkflow::class);

    $workflow->cancelByAdmin($this->order);
    expect($this->order->refresh()->status)->toBe(OrderStatus::Cancelled);

    Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
});

test('admin cancellation rejects a started order with a domain exception', function () {
    $this->order->update(['status' => OrderStatus::InProgress]);

    expect(fn () => app(OrderWorkflow::class)->cancelByAdmin($this->order))
        ->toThrow(InvalidOrderTransition::class);
    expect($this->order->refresh()->status)->toBe(OrderStatus::InProgress);
});

test('order card renders snapshot data line items and empty dependent sections', function () {
    $this->order->addressSnapshot()->create([
        'full_address' => 'Иркутск, Советская, 1',
        'entrance' => '2',
        'floor' => '5',
        'apartment' => '42',
    ]);
    $this->order->lineItems()->createMany([
        ['kind' => 'base', 'title' => 'Командная уборка', 'amount' => 5000],
        ['kind' => 'extra_option', 'title' => 'Помыть холодильник', 'amount' => 800],
    ]);

    Livewire::test(ViewCleaningOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertOk()
        ->assertSee('Анна Клиентова')
        ->assertSee('Иркутск, Советская, 1')
        ->assertSee('Помыть холодильник')
        ->assertSee('Попыток оплаты нет')
        ->assertSee('Жалоб нет');
});

test('order card and read only relation show payment attempts including errors', function () {
    $attempt = PaymentAttempt::query()->create([
        'cleaning_order_id' => $this->order->id,
        'provider' => 'tbank',
        'external_order_id' => 'pay_01J2QM1R7H7YV9JH1KACD6ZM01',
        'amount' => 500000,
        'currency' => 'RUB',
        'status' => 'failed',
        'error_code' => '1001',
        'error_message' => 'Платёж отклонён',
    ]);

    Livewire::test(ViewCleaningOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertSee('tbank')
        ->assertSee('Платёж отклонён');

    Livewire::test(PaymentAttemptsRelationManager::class, [
        'ownerRecord' => $this->order,
        'pageClass' => ViewCleaningOrder::class,
    ])->assertCanSeeTableRecords([$attempt])
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit', record: $attempt)
        ->assertTableActionDoesNotExist('delete', record: $attempt);
});

test('order list filters by service cleaner period and empty team', function () {
    $cleaner = activeCleaner('Первый клинер', '+79990000002');
    $this->order->cleaners()->attach($cleaner->id, ['accepted_at' => now()]);
    $otherService = CleaningService::query()->create([
        'name' => 'Другая услуга', 'slug' => 'other', 'base_price' => 2000,
    ]);
    $otherOrder = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZM02',
        'client_id' => $this->client->id,
        'cleaning_service_id' => $otherService->id,
        'status' => OrderStatus::Processing,
        'address' => 'Иркутск',
        'scheduled_at' => now()->addMonth(),
        'total_price' => 2000,
    ]);

    Livewire::test(ListCleaningOrders::class)
        ->filterTable('service', $this->service->id)
        ->assertCanSeeTableRecords([$this->order])
        ->assertCanNotSeeTableRecords([$otherOrder])
        ->resetTableFilters()
        ->filterTable('cleaner', $cleaner->id)
        ->assertCanSeeTableRecords([$this->order])
        ->assertCanNotSeeTableRecords([$otherOrder])
        ->resetTableFilters()
        ->filterTable('without_cleaners', true)
        ->assertCanSeeTableRecords([$otherOrder])
        ->assertCanNotSeeTableRecords([$this->order])
        ->resetTableFilters()
        ->filterTable('scheduled_at', [
            'from' => now()->startOfDay()->toDateString(),
            'until' => now()->addWeek()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$this->order])
        ->assertCanNotSeeTableRecords([$otherOrder]);
});

test('cleaner relation manager assigns active cleaners and hides removal after start', function () {
    $cleaner = activeCleaner('Первый клинер', '+79990000002');

    $manager = Livewire::test(CleanersRelationManager::class, [
        'ownerRecord' => $this->order,
        'pageClass' => ViewCleaningOrder::class,
    ])->callTableAction('assignCleaner', data: ['cleaner_id' => $cleaner->id])
        ->assertHasNoFormErrors()
        ->assertNotified('Клинер назначен');

    $assigned = $this->order->cleaners()->findOrFail($cleaner->id);
    $manager->assertCanSeeTableRecords([$assigned]);

    $this->order->cleaners()->updateExistingPivot($cleaner->id, ['started_at' => now()]);
    $manager->assertTableActionHidden('removeCleaner', $assigned);
});

function activeCleaner(string $name, string $phone, bool $active = true): User
{
    $cleaner = User::factory()->create([
        'role' => UserRole::Cleaner,
        'name' => $name,
        'phone' => $phone,
    ]);
    $cleaner->cleanerProfile()->create([
        'name' => $name,
        'access_code_hash' => Hash::make('123456'),
        'is_active' => $active,
    ]);

    return $cleaner;
}
