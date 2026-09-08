<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Actions\OrderWorkflowAction;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Models\CleaningOrder;
use App\Models\User;
use App\Modules\Orders\Actions\AssignCleaner;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CleanersRelationManager extends RelationManager
{
    protected static string $relationship = 'cleaners';

    protected static ?string $title = 'Команда клинеров';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Имя')->placeholder('Не указано')->searchable(),
                TextColumn::make('phone')->label('Телефон')->searchable(),
                TextColumn::make('pivot.accepted_at')->label('Принял')->dateTime()->placeholder('—'),
                TextColumn::make('pivot.started_at')->label('Начал')->dateTime()->placeholder('—'),
                TextColumn::make('pivot.completed_at')->label('Завершил')->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                OrderWorkflowAction::make('assignCleaner')
                    ->label('Назначить клинера')
                    ->authorize(fn (): bool => CleaningOrderResource::canEdit($this->getOwnerRecord()))
                    ->visible(fn (): bool => $this->canAssign())
                    ->schema([
                        Select::make('cleaner_id')
                            ->label('Активный клинер')
                            ->options(fn (): array => $this->activeCleanerOptions())
                            ->searchable()
                            ->required()
                            ->helperText('Уже назначенные и неактивные клинеры исключены из списка.'),
                    ])
                    ->action(function (array $data, AssignCleaner $assigner): void {
                        $cleaner = User::query()->findOrFail($data['cleaner_id']);
                        $assigner->assign($this->getOwnerRecord(), $cleaner);
                        $this->getOwnerRecord()->refresh();

                        Notification::make()->title('Клинер назначен')->success()->send();
                    }),
            ])
            ->recordActions([
                OrderWorkflowAction::make('removeCleaner')
                    ->label('Снять')
                    ->color('danger')
                    ->authorize(fn (): bool => CleaningOrderResource::canEdit($this->getOwnerRecord()))
                    ->visible(fn (User $record): bool => $this->canRemove($record))
                    ->requiresConfirmation()
                    ->action(function (User $record, AssignCleaner $assigner): void {
                        $assigner->remove($this->getOwnerRecord(), $record);
                        $this->getOwnerRecord()->refresh();

                        Notification::make()->title('Клинер снят с заказа')->success()->send();
                    }),
            ]);
    }

    private function canAssign(): bool
    {
        /** @var CleaningOrder $order */
        $order = $this->getOwnerRecord();

        return $order->status === OrderStatus::Confirmed
            && $order->cleaners()->count() < $order->service()->value('required_cleaners');
    }

    private function canRemove(User $cleaner): bool
    {
        /** @var CleaningOrder $order */
        $order = $this->getOwnerRecord();

        return in_array($order->status, [OrderStatus::Confirmed, OrderStatus::TeamFormed], true)
            && $cleaner->pivot->started_at === null;
    }

    /** @return array<int, string> */
    private function activeCleanerOptions(): array
    {
        /** @var CleaningOrder $order */
        $order = $this->getOwnerRecord();

        return User::query()
            ->where('role', UserRole::Cleaner)
            ->whereHas('cleanerProfile', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('cleaningOrders', fn ($query) => $query->whereKey($order->getKey()))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $cleaner): array => [$cleaner->id => $cleaner->name ?: $cleaner->phone])
            ->all();
    }
}
