<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningServices\Pages\CreateCleaningService;
use App\Filament\Resources\CleaningServices\Pages\EditCleaningService;
use App\Filament\Resources\CleaningServices\Pages\ListCleaningServices;
use App\Filament\Resources\CleaningServices\RelationManagers\OptionsRelationManager;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\ServiceOption;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

test('service with an order cannot be deleted from admin', function () {
    $service = CleaningService::query()->create([
        'name' => 'Уборка', 'slug' => 'standard', 'base_price' => 1000,
    ]);
    $client = User::factory()->create(['role' => UserRole::Client]);
    $order = CleaningOrder::query()->create([
        'public_id' => (string) Str::ulid(),
        'client_id' => $client->id,
        'cleaning_service_id' => $service->id,
        'status' => OrderStatus::Processing,
        'address' => 'Иркутск, Ленина, 1',
        'scheduled_at' => now()->addDay(),
        'total_price' => 1000,
    ]);

    Livewire::test(EditCleaningService::class, ['record' => $service->id])
        ->callAction('delete')
        ->assertNotified('Услугу нельзя удалить');

    expect($service->fresh())->not->toBeNull()
        ->and($order->fresh())->not->toBeNull();
});

test('service without orders can be deleted from admin', function () {
    $service = CleaningService::query()->create([
        'name' => 'Уборка', 'slug' => 'temporary', 'base_price' => 1000,
    ]);

    Livewire::test(EditCleaningService::class, ['record' => $service->id])
        ->callAction('delete');

    expect($service->fresh())->toBeNull();
});

test('inactive service is omitted from the public catalog', function () {
    CleaningService::query()->create([
        'name' => 'Активная', 'slug' => 'active', 'base_price' => 1000, 'is_active' => true,
    ]);
    CleaningService::query()->create([
        'name' => 'Неактивная', 'slug' => 'inactive', 'base_price' => 1000, 'is_active' => false,
    ]);

    $this->getJson('/api/v1/client/services')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'active')
        ->assertJsonMissing(['id' => 'inactive']);
});

test('service list filters toggles and reorders records', function () {
    $active = CleaningService::query()->create([
        'name' => 'Активная', 'slug' => 'active-list', 'base_price' => 1000, 'sort_order' => 10, 'is_active' => true,
    ]);
    $inactive = CleaningService::query()->create([
        'name' => 'Неактивная', 'slug' => 'inactive-list', 'base_price' => 2000, 'sort_order' => 20, 'is_active' => false,
    ]);
    $active->options()->create(['code' => 'room-1', 'group' => 'room', 'title' => 'Одна комната']);

    Livewire::test(ListCleaningServices::class)
        ->assertTableColumnExists('image_url')
        ->assertTableColumnExists('options_count')
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->resetTableFilters()
        ->call('reorderTable', [$inactive->id, $active->id])
        ->call('updateTableColumnState', 'is_active', (string) $active->id, false);

    expect($inactive->refresh()->sort_order)->toBe(1)
        ->and($active->refresh()->sort_order)->toBe(2)
        ->and($active->is_active)->toBeFalse();
});

test('service form follows the fields used by the mobile app', function () {
    Livewire::test(CreateCleaningService::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('subtitle')
        ->assertFormFieldExists('cleaners_label')
        ->assertFormFieldExists('duration_label')
        ->assertFormFieldExists('long_description')
        ->assertFormFieldExists('base_price')
        ->assertFormFieldDoesNotExist('price_per_sqm')
        ->assertFormFieldDoesNotExist('min_area')
        ->assertFormFieldDoesNotExist('max_area')
        ->assertFormFieldDoesNotExist('area_step')
        ->fillForm([
            'name' => 'Уборка',
            'slug' => 'mobile-format',
            'base_price' => 1000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $service = CleaningService::query()->where('slug', 'mobile-format')->firstOrFail();
    expect($service->min_price)->toBe(1000);

    Livewire::test(EditCleaningService::class, ['record' => $service->id])
        ->fillForm(['base_price' => 1200])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($service->refresh()->base_price)->toBe(1200)
        ->and($service->min_price)->toBe(1200);
    $this->getJson('/api/v1/client/services/mobile-format')
        ->assertOk()
        ->assertJsonPath('data.price_from', 1200);
});

test('service options are grouped in tabs and dependencies are saved', function () {
    $service = CleaningService::query()->create([
        'name' => 'Уборка', 'slug' => 'options', 'base_price' => 1000,
    ]);
    $room = ServiceOption::query()->create([
        'cleaning_service_id' => $service->id,
        'code' => 'room-1',
        'group' => 'room',
        'title' => 'Одна комната',
    ]);
    $extra = ServiceOption::query()->create([
        'cleaning_service_id' => $service->id,
        'code' => 'oven',
        'group' => 'extra',
        'title' => 'Духовка',
    ]);

    Livewire::test(OptionsRelationManager::class, [
        'ownerRecord' => $service,
        'pageClass' => EditCleaningService::class,
    ])->assertCanSeeTableRecords([$room])
        ->assertCanNotSeeTableRecords([$extra])
        ->set('activeTab', 'extra')
        ->assertCanSeeTableRecords([$extra])
        ->assertCanNotSeeTableRecords([$room])
        ->callTableAction('create', data: [
            'group' => 'extra',
            'code' => 'windows',
            'title' => 'Окна',
            'price_modifier' => 800,
            'cleaner_revenue_percent' => 50,
            'checklist_zone' => 'all',
            'sort_order' => 20,
            'is_addon' => false,
            'is_active' => true,
            'allowedWith' => [$room->id],
        ])->assertHasNoFormErrors();

    $windows = ServiceOption::query()->where('code', 'windows')->firstOrFail();
    expect($windows->group)->toBe('extra')
        ->and($windows->is_addon)->toBeTrue()
        ->and($windows->allowedWith()->pluck('service_options.id')->all())->toBe([$room->id]);
});
