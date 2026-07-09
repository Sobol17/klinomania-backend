<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateCleanerCode')
                ->label('Generate cleaner code')
                ->visible(fn (): bool => $this->record->role === UserRole::Cleaner)
                ->action(function (): void {
                    $code = (string) random_int(100000, 999999);

                    $this->record->cleanerProfile()->updateOrCreate([], [
                        'access_code_hash' => Hash::make($code),
                        'is_active' => true,
                    ]);

                    Notification::make()
                        ->title("Cleaner code: {$code}")
                        ->warning()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->role === UserRole::Cleaner) {
            $this->record->cleanerProfile()->firstOrCreate([]);
        }

        if ($this->record->role === UserRole::Client) {
            $this->record->clientProfile()->firstOrCreate([]);
        }
    }
}
