<?php

namespace App\Filament\Resources\CleaningOrders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Actions\OrderWorkflowAction;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Modules\Orders\Actions\OrderWorkflow;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCleaningOrder extends ViewRecord
{
    protected static string $resource = CleaningOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderWorkflowAction::make('confirm')
                ->label('Подтвердить заявку')
                ->authorize(fn (): bool => static::getResource()::canEdit($this->record))
                ->color('success')
                ->visible(fn (): bool => $this->record->status === OrderStatus::Processing)
                ->action(function (OrderWorkflow $workflow): void {
                    $workflow->confirm($this->record);
                    $this->record->refresh();

                    Notification::make()->title('Заявка подтверждена')->success()->send();
                }),
            OrderWorkflowAction::make('cancel')
                ->label('Отменить заявку')
                ->authorize(fn (): bool => static::getResource()::canEdit($this->record))
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [OrderStatus::Processing, OrderStatus::Confirmed], true))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->record->cleaners()->exists()
                    ? 'На заказ уже назначены клинеры. После отмены точечно снять начавших работу клинеров нельзя.'
                    : 'Клиент получит уведомление об отмене заказа.')
                ->action(function (OrderWorkflow $workflow): void {
                    $workflow->cancelByAdmin($this->record);
                    $this->record->refresh();

                    Notification::make()->title('Заявка отменена')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
