<?php

namespace App\Filament\Resources\CleaningServices\Pages;

use App\Filament\Resources\CleaningServices\CleaningServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditCleaningService extends EditRecord
{
    protected static string $resource = CleaningServiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['min_price'] = $data['base_price'];

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [CleaningServiceResource::deleteAction()];
    }
}
