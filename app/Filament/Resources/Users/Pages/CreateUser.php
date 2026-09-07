<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Modules\Identity\Actions\ResetCleanerAccessCode;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        if ($this->record->role === UserRole::Cleaner) {
            $code = app(ResetCleanerAccessCode::class)->execute($this->record);
            Notification::make()->title('Код доступа клинера')->body($code)->persistent()->success()->send();
        }
    }
}
