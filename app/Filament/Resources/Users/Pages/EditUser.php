<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\ResetCleanerAccessCodeAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ResetCleanerAccessCodeAction::make(), DeleteAction::make()];
    }
}
