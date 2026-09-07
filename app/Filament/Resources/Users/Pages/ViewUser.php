<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\ResetCleanerAccessCodeAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ResetCleanerAccessCodeAction::make()];
    }
}
