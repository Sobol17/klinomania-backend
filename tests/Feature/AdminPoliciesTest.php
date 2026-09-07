<?php

use App\Enums\UserRole;
use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\PaymentAttempt;
use App\Models\ServiceOption;
use App\Models\User;
use App\Policies\CleaningServicePolicy;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('resource policies are discovered and restrict administration to admins', function (string $model) {
    expect(Gate::getPolicyFor($model))->not->toBeNull();

    foreach (UserRole::cases() as $role) {
        $user = User::factory()->make(['role' => $role]);
        $gate = Gate::forUser($user);
        $allowed = $role === UserRole::Admin;

        foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
            expect($gate->allows($ability, $model))->toBe($allowed);
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            expect($gate->allows($ability, new $model))->toBe($allowed);
        }
        expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($allowed);
    }

    expect(Gate::forUser(null)->allows('viewAny', $model))->toBeFalse();
})->with([User::class, CleaningService::class, CleaningOrder::class, ServiceOption::class]);

test('payments are read only even for administrators', function () {
    expect(Gate::getPolicyFor(PaymentAttempt::class))->not->toBeNull();

    foreach (UserRole::cases() as $role) {
        $gate = Gate::forUser(User::factory()->make(['role' => $role]));
        $payment = new PaymentAttempt;

        expect($gate->allows('viewAny', PaymentAttempt::class))->toBe($role === UserRole::Admin)
            ->and($gate->allows('view', $payment))->toBe($role === UserRole::Admin);
        foreach (['create', 'deleteAny', 'restoreAny', 'forceDeleteAny'] as $ability) {
            expect($gate->allows($ability, PaymentAttempt::class))->toBeFalse();
        }
        foreach (['update', 'delete', 'restore', 'forceDelete', 'replicate'] as $ability) {
            expect($gate->allows($ability, $payment))->toBeFalse();
        }
    }

    expect(Gate::forUser(null)->allows('view', new PaymentAttempt))->toBeFalse();
});

test('panel resource routes reject clients and cleaners', function (UserRole $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    foreach (['users', 'cleaning-services', 'cleaning-orders', 'extra-services'] as $resource) {
        $this->get('/admin/'.$resource)->assertForbidden();
        $this->get('/admin/'.$resource.'/create')->assertForbidden();
    }
})->with([UserRole::Client, UserRole::Cleaner]);

test('Filament enforces a resource policy even when the user can enter the panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $service = CleaningService::query()->create([
        'name' => 'Уборка', 'slug' => 'standard', 'base_price' => 1000,
    ]);
    $this->app->instance(CleaningServicePolicy::class, new class extends CleaningServicePolicy
    {
        public function create(User $user): bool
        {
            return false;
        }

        public function update(User $user, Model $record): bool
        {
            return false;
        }

        public function delete(User $user, Model $record): bool
        {
            return false;
        }
    });

    $this->get('/admin/cleaning-services')->assertOk();
    $this->get('/admin/cleaning-services/create')->assertForbidden();
    $this->get('/admin/cleaning-services/'.$service->getRouteKey().'/edit')->assertForbidden();
    expect(CleaningServiceResource::canCreate())->toBeFalse()
        ->and(CleaningServiceResource::canEdit($service))->toBeFalse()
        ->and(CleaningServiceResource::canDelete($service))->toBeFalse();
});
