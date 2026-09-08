<?php

use App\Enums\UserRole;
use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use App\Filament\Resources\CleaningServices\Pages\CreateCleaningService;
use App\Models\CleaningService;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin image upload stores the file and persists its full public URL', function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $image = UploadedFile::fake()->image('hero.jpg', 1200, 800);
    $gallery = [
        UploadedFile::fake()->image('first.jpg', 1200, 800),
        UploadedFile::fake()->image('second.png', 1200, 800),
    ];

    Livewire::test(CreateCleaningService::class)
        ->fillForm([
            'name' => 'Тестовая уборка',
            'slug' => 'test-service',
            'base_price' => 1500,
            'image_url' => $image,
            'gallery' => $gallery,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $service = CleaningService::query()->where('slug', 'test-service')->firstOrFail();
    $path = CleaningServiceResource::publicStoragePath($service->image_url);

    expect($service->image_url)->toStartWith(config('filesystems.disks.public.url').'/services/')
        ->and($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
    expect($service->gallery)->toHaveCount(2)
        ->and($service->gallery[0])->toStartWith(config('filesystems.disks.public.url').'/services/')
        ->and($service->gallery[1])->toStartWith(config('filesystems.disks.public.url').'/services/');
    foreach ($service->gallery as $galleryUrl) {
        Storage::disk('public')->assertExists(CleaningServiceResource::publicStoragePath($galleryUrl));
    }

    $this->getJson('/api/v1/client/services/test-service')
        ->assertOk()
        ->assertJsonPath('data.image_url', $service->image_url)
        ->assertJsonPath('data.gallery', $service->gallery);
});
