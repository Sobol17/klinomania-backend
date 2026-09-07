<?php

use App\Enums\ChecklistZone;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use App\Filament\Resources\ExtraServices\ExtraServiceResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

test('the application and Filament resources use Russian labels', function () {
    expect(app()->getLocale())->toBe('ru')
        ->and(config('app.fallback_locale'))->toBe('ru')
        ->and(UserResource::getNavigationLabel())->toBe('Пользователи')
        ->and(UserResource::getModelLabel())->toBe('пользователя')
        ->and(CleaningServiceResource::getNavigationLabel())->toBe('Услуги уборки')
        ->and(CleaningServiceResource::getModelLabel())->toBe('услугу уборки')
        ->and(CleaningOrderResource::getNavigationLabel())->toBe('Заявки')
        ->and(CleaningOrderResource::getModelLabel())->toBe('заявку')
        ->and(ExtraServiceResource::getNavigationLabel())->toBe('Дополнительные услуги');
});

test('panel enums provide Russian labels and colors for every case', function () {
    foreach ([UserRole::class, OrderStatus::class, ChecklistZone::class] as $enum) {
        foreach ($enum::cases() as $case) {
            expect($case)->toBeInstanceOf(HasLabel::class)
                ->toBeInstanceOf(HasColor::class)
                ->and($case->getLabel())->toMatch('/[А-Яа-яЁё]/u')
                ->and($case->getColor())->toBeIn(['gray', 'primary', 'info', 'success', 'warning', 'danger']);
        }
    }
});
