<?php

namespace App\Filament\Resources\CleaningOrders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Modules\Orders\Actions\OrderWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCleaningOrder extends EditRecord
{
    protected static string $resource = CleaningOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Подтвердить заявку')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === OrderStatus::Processing)
                ->action(function (OrderWorkflow $workflow): void {
                    $workflow->confirm($this->record);
                    $this->record->refresh();

                    Notification::make()->title('Заявка подтверждена')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->addressSnapshot()->updateOrCreate([], [
            'full_address' => $this->record->address,
            'comment' => $this->record->comment,
        ]);
    }
}
