<?php

use App\Models\CleaningService;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('service catalog seeder creates three complete active services', function () {
    $this->seed(ServiceCatalogSeeder::class);

    expect(CleaningService::query()->orderBy('sort_order')->pluck('slug')->all())->toBe(['standard', 'premium', 'cottage']);

    CleaningService::query()->with('options')->each(function (CleaningService $service): void {
        expect($service->name)->not->toBeEmpty()
            ->and($service->subtitle)->not->toBeEmpty()
            ->and($service->description)->not->toBeEmpty()
            ->and($service->short_description)->not->toBeEmpty()
            ->and($service->long_description)->not->toBeEmpty()
            ->and($service->cleaners_label)->not->toBeEmpty()
            ->and($service->duration_label)->not->toBeEmpty()
            ->and($service->image_url)->not->toBeEmpty()
            ->and($service->gallery)->not->toBeEmpty()
            ->and($service->options)->not->toBeEmpty();
    });
});
