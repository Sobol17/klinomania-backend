<?php

namespace App\Filament\Resources\ExtraServices\Pages;

use App\Filament\Resources\ExtraServices\ExtraServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtraService extends EditRecord
{
    protected static string $resource = ExtraServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
