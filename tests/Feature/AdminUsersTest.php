<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ClientOrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Models\CleanerProfile;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Identity\Actions\ResetCleanerAccessCode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

test('users can be filtered by role tabs and searched by phone', function () {
    $client = User::factory()->create(['role' => UserRole::Client, 'phone' => '+79991234567']);
    $cleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    Livewire::test(ListUsers::class)->set('activeTab', 'client')
        ->assertCanSeeTableRecords([$client])->assertCanNotSeeTableRecords([$cleaner])
        ->searchTable('1234567')->assertCanSeeTableRecords([$client]);
    Livewire::test(ListUsers::class)->filterTable('role', 'cleaner')
        ->assertCanSeeTableRecords([$cleaner])->assertCanNotSeeTableRecords([$client]);
});

test('client creation persists the selected profile only', function () {
    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Клиент', 'phone' => '+79991234567', 'role' => 'client',
        'clientProfile' => ['name' => 'Имя профиля', 'address' => 'Ленина, 1', 'push_notifications_enabled' => false, 'email_marketing_enabled' => true],
    ])->call('create')->assertHasNoFormErrors();
    $user = User::where('phone', '+79991234567')->firstOrFail();
    expect($user->clientProfile->address)->toBe('Ленина, 1')
        ->and($user->clientProfile->email_marketing_enabled)->toBeTrue()
        ->and($user->cleanerProfile)->toBeNull();
});

test('cleaner creation generates a hashed code and renders the card', function () {
    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Клинер', 'phone' => '+79991234567', 'role' => 'cleaner',
        'cleanerProfile' => ['name' => 'Имя клинера', 'is_active' => true],
    ])->call('create')->assertHasNoFormErrors()->assertNotified('Код доступа клинера');
    $user = User::where('phone', '+79991234567')->firstOrFail();
    expect($user->cleanerProfile->access_code_hash)->not->toBeNull()->and($user->clientProfile)->toBeNull();
    Livewire::test(ViewUser::class, ['record' => $user->id])->assertSuccessful()->assertSee('Имя клинера');
});

test('editing preserves a blank password and saves the profile', function () {
    $user = User::factory()->create(['role' => UserRole::Client]);
    $user->clientProfile()->create(['name' => 'Прежнее имя']);
    $hash = $user->password;
    Livewire::test(EditUser::class, ['record' => $user->id])->fillForm([
        'password' => '', 'clientProfile.name' => 'Новое имя',
    ])->call('save')->assertHasNoFormErrors();
    expect($user->refresh()->password)->toBe($hash)->and($user->clientProfile->name)->toBe('Новое имя');
});

test('reset replaces credentials without changing activity and is unavailable to clients', function () {
    config(['klinomania.auth.cleaner_code_stub_enabled' => false]);
    $user = User::factory()->create(['role' => UserRole::Cleaner, 'phone' => '+79991234567']);
    $user->cleanerProfile()->create(['is_active' => true, 'access_code_hash' => Hash::make('123456')]);
    $code = app(ResetCleanerAccessCode::class)->execute($user);
    $this->postJson('/api/v1/cleaner/auth/login', ['phone' => $user->phone, 'code' => '123456'])->assertStatus(422);
    $this->postJson('/api/v1/cleaner/auth/login', ['phone' => $user->phone, 'code' => $code])->assertOk();
    $user->cleanerProfile->update(['is_active' => false]);
    Livewire::test(EditUser::class, ['record' => $user->id])->callAction('resetCleanerAccessCode')->assertNotified('Новый код клинера');
    expect($user->cleanerProfile->refresh()->is_active)->toBeFalse();
    $client = User::factory()->create(['role' => UserRole::Client]);
    Livewire::test(ViewUser::class, ['record' => $client->id])->assertActionHidden('resetCleanerAccessCode');
});

test('changing role creates the new profile and preserves the old one', function () {
    $user = User::factory()->create(['role' => UserRole::Client]);
    $user->clientProfile()->create(['address' => 'Старый адрес']);
    Livewire::test(EditUser::class, ['record' => $user->id])->fillForm([
        'role' => 'cleaner', 'cleanerProfile' => ['name' => 'Клинер', 'is_active' => true],
    ])->call('save')->assertHasNoFormErrors();
    expect($user->refresh()->role)->toBe(UserRole::Cleaner)
        ->and($user->cleanerProfile->name)->toBe('Клинер')
        ->and($user->clientProfile->address)->toBe('Старый адрес');
});

test('profile failure rolls back user creation', function () {
    $this->mock(ResetCleanerAccessCode::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('Failed'));
    try {
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Клинер', 'phone' => '+79991234567', 'role' => 'cleaner',
            'cleanerProfile' => ['is_active' => true],
        ])->call('create');
        $this->fail('Expected failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Failed');
    }
    expect(User::where('phone', '+79991234567')->exists())->toBeFalse()
        ->and(CleanerProfile::count())->toBe(0);
});

test('order history includes only orders belonging to the selected user', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);
    $other = User::factory()->create(['role' => UserRole::Client]);
    $cleaner = User::factory()->create(['role' => UserRole::Cleaner]);
    $service = CleaningService::create(['name' => 'Уборка', 'slug' => 'test', 'base_price' => 1000]);
    $orders = collect([$client, $other])->map(fn ($user) => CleaningOrder::create([
        'public_id' => (string) Str::ulid(), 'client_id' => $user->id,
        'cleaning_service_id' => $service->id, 'status' => OrderStatus::Processing,
        'address' => 'Ленина, 1', 'scheduled_at' => now()->addDay(), 'total_price' => 1000,
    ]));
    $orders[0]->cleaners()->attach($cleaner);
    foreach ([[$client, ClientOrdersRelationManager::class], [$cleaner, OrdersRelationManager::class]] as [$owner, $manager]) {
        Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords([$orders[0]])->assertCanNotSeeTableRecords([$orders[1]]);
    }
});

test('duplicate phone is rejected and non admins cannot open user management', function () {
    $client = User::factory()->create(['role' => UserRole::Client, 'phone' => '+79991234567']);
    Livewire::test(CreateUser::class)->fillForm(['role' => 'client', 'phone' => $client->phone])
        ->call('create')->assertHasFormErrors(['phone' => 'unique']);
    $this->actingAs($client);
    Livewire::test(ListUsers::class)->assertForbidden();
    Livewire::test(EditUser::class, ['record' => $client->id])->assertForbidden();
});
