<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Actions\OrderWorkflowAction;
use App\Filament\Resources\CleaningOrders\Pages\EditCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Orders\Actions\OrderWorkflow;
use App\Modules\Orders\Exceptions\ChecklistIncomplete;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $client = User::factory()->create(['role' => UserRole::Client, 'phone' => '+79990000001']);
    $service = CleaningService::query()->create([
        'name' => 'Уборка', 'slug' => 'standard', 'base_price' => 1000,
    ]);
    $this->order = CleaningOrder::query()->create([
        'public_id' => '01J2QM1R7H7YV9JH1KACD6ZK3R', 'client_id' => $client->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::Processing,
        'address' => 'Иркутск, Ленина, 10', 'scheduled_at' => now()->addDay(), 'total_price' => 1000,
    ]);
});

test('order table renders enum labels and filters by enum values', function () {
    Livewire::test(ListCleaningOrders::class)
        ->assertCanSeeTableRecords([$this->order])
        ->assertSee(OrderStatus::Processing->getLabel())
        ->filterTable('status', OrderStatus::Completed->value)
        ->assertCanNotSeeTableRecords([$this->order]);
});

test('admin confirmation calls the workflow and persists the transition', function () {
    Livewire::test(EditCleaningOrder::class, ['record' => $this->order->getRouteKey()])
        ->callAction('confirm')
        ->assertNotified('Заявка подтверждена');

    expect($this->order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a concurrent order change shows an admin notification without overwriting the status', function () {
    $page = Livewire::test(EditCleaningOrder::class, ['record' => $this->order->getRouteKey()]);
    $workflow = new OrderWorkflow;
    $this->mock(OrderWorkflow::class, function ($mock) use ($workflow) {
        $mock->shouldReceive('confirm')->once()->andReturnUsing(function (CleaningOrder $order) use ($workflow) {
            CleaningOrder::query()->whereKey($order->id)->update(['status' => OrderStatus::Cancelled]);

            return $workflow->confirm($order);
        });
    });

    $page->callAction('confirm')
        ->assertNotified('Операция недоступна в текущем состоянии заявки');

    expect($this->order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('invalid transitions throw a domain exception without changing the order', function () {
    $this->order->update(['status' => OrderStatus::Cancelled]);

    expect(fn () => app(OrderWorkflow::class)->confirm($this->order))
        ->toThrow(InvalidOrderTransition::class);
    expect($this->order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('workflow actions halt with a danger notification for incomplete checklists', function () {
    $action = OrderWorkflowAction::make('complete')->action(fn () => throw new ChecklistIncomplete);

    expect(fn () => $action->call())->toThrow(Halt::class);
    Notification::assertNotified(Notification::make()
        ->title('Сначала выполните все пункты чек-листа')
        ->body('Обновите страницу и проверьте состояние заявки.')
        ->danger());
});
