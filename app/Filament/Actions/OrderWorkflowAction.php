<?php

namespace App\Filament\Actions;

use App\Modules\Orders\Exceptions\ChecklistIncomplete;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class OrderWorkflowAction extends Action
{
    public function call(array $parameters = []): mixed
    {
        try {
            return parent::call($parameters);
        } catch (InvalidOrderTransition|ChecklistIncomplete $exception) {
            Notification::make()
                ->title($exception instanceof ChecklistIncomplete
                    ? 'Сначала выполните все пункты чек-листа'
                    : 'Операция недоступна в текущем состоянии заявки')
                ->body('Обновите страницу и проверьте состояние заявки.')
                ->danger()
                ->send();

            $this->halt(shouldRollBackDatabaseTransaction: true);
        }
    }
}
