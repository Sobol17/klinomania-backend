<?php

namespace Database\Factories;

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CleaningOrder;
use App\Models\CleaningService;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Complaint> */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'client_id' => User::factory()->state(['role' => UserRole::Client]),
            'cleaning_order_id' => function (array $attributes): int {
                $service = CleaningService::query()->firstOrCreate(
                    ['slug' => 'factory-service'],
                    ['name' => 'Тестовая уборка', 'base_price' => 1000, 'min_price' => 1000],
                );

                return CleaningOrder::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'client_id' => $attributes['client_id'],
                    'cleaning_service_id' => $service->id,
                    'status' => OrderStatus::Completed,
                    'address' => fake()->address(),
                    'scheduled_at' => now()->subDay(),
                    'total_price' => 1000,
                    'currency' => 'RUB',
                ])->id;
            },
            'status' => ComplaintStatus::New,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
        ];
    }
}
