<?php

namespace App\Filament\Resources\ExtraServices\Pages;

use App\Filament\Resources\ExtraServices\ExtraServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraServices extends ListRecords
{
    protected static string $resource = ExtraServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
