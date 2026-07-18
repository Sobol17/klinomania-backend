<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin seeder creates the Filament administrator from configuration', function () {
    config()->set('klinomania.admin.email', 'admin@klinomania.test');
    config()->set('klinomania.admin.password', 'test-password');

    $this->seed(AdminSeeder::class);

    $admin = User::query()->where('email', 'admin@klinomania.test')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and(Hash::check('test-password', $admin->password))->toBeTrue();
});
