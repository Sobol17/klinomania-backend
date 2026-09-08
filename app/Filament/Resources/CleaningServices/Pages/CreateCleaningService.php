<?php

namespace App\Filament\Resources\CleaningServices\Pages;

use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCleaningService extends CreateRecord
{
    protected static string $resource = CleaningServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['min_price'] = $data['base_price'];

        return $data;
    }
}
