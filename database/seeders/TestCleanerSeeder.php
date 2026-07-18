<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestCleanerSeeder extends Seeder
{
    public function run(): void
    {
        $cleaner = User::query()->updateOrCreate(
            ['phone' => '+79999999999'],
            ['name' => 'Тестовый клинер', 'role' => UserRole::Cleaner],
        );

        $cleaner->cleanerProfile()->updateOrCreate([], [
            'name' => 'Тестовый клинер',
            'access_code_hash' => Hash::make('111111'),
            'is_active' => true,
        ]);
    }
}
