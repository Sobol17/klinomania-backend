<?php

use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use App\Filament\Resources\ExtraServices\ExtraServiceResource;
use App\Filament\Resources\Users\UserResource;

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
