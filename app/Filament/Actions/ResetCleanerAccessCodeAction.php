<?php

namespace App\Filament\Actions;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Identity\Actions\ResetCleanerAccessCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ResetCleanerAccessCodeAction
{
    public static function make(): Action
    {
        return Action::make('resetCleanerAccessCode')
            ->label('Сбросить код доступа клинера')
            ->authorize(fn (User $record): bool => UserResource::canEdit($record))
            ->visible(fn (User $record): bool => $record->role === UserRole::Cleaner && $record->cleanerProfile !== null)
            ->requiresConfirmation()
            ->modalDescription('Прежний код перестанет работать. Новый код будет показан один раз. В режиме тестового входа используется код из конфигурации.')
            ->action(function (User $record): void {
                $code = app(ResetCleanerAccessCode::class)->execute($record);
                Notification::make()->title('Новый код клинера')->body($code)->persistent()->success()->send();
            });
    }
}
