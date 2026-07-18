<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\TestCleanerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('test cleaner seeder creates an active cleaner with the configured test phone', function () {
    $this->seed(TestCleanerSeeder::class);

    $cleaner = User::query()->where('phone', '+79999999999')->sole();

    expect($cleaner->role)->toBe(UserRole::Cleaner)
        ->and($cleaner->cleanerProfile->is_active)->toBeTrue()
        ->and(Hash::check('111111', $cleaner->cleanerProfile->access_code_hash))->toBeTrue();
});
