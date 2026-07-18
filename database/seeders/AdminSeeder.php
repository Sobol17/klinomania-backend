<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('klinomania.admin.email')],
            [
                'name' => 'Администратор',
                'password' => Hash::make(config('klinomania.admin.password')),
                'role' => UserRole::Admin,
            ],
        );
    }
}
