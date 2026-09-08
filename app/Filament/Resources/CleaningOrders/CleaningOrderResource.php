<?php

namespace App\Filament\Resources\CleaningOrders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\Pages\CreateCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\EditCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Filament\Resources\CleaningOrders\Pages\ViewCleaningOrder;
use App\Filament\Resources\CleaningOrders\RelationManagers\ChecklistRelationManager;
use App\Filament\Resources\CleaningOrders\RelationManagers\CleanersRelationManager;
use App\Filament\Resources\CleaningOrders\RelationManagers\LineItemsRelationManager;
use App\Filament\Resources\CleaningOrders\RelationManagers\PaymentAttemptsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\CleaningOrder;
use App\Models\ServiceOption;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CleaningOrderResource extends Resource
{
    protected static ?string $model = CleaningOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Заявки';

    protected static ?string $modelLabel = 'заявку';

    protected static ?string $pluralModelLabel = 'заявки';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->label('Клиент')
                ->options(fn (): array => User::query()->where('role', UserRole::Client)->orderBy('phone')->pluck('phone', 'id')->all())
                ->required()
                ->disabledOn('edit'),
            Select::make('cleaning_service_id')
                ->label('Услуга')
                ->relationship('service', 'name')
                ->required()
                ->live()
                ->disabledOn('edit'),
            Select::make('room_option_id')
                ->label('Количество комнат')
                ->options(fn (Get $get): array => self::optionsForService($get->integer('cleaning_service_id', true), 'room'))
                ->visibleOn('create'),
            Select::make('cleaning_option_id')
                ->label('Тип уборки')
                ->options(fn (Get $get): array => self::optionsForService($get->integer('cleaning_service_id', true), 'cleaning'))
                ->visibleOn('create'),
            Select::make('extra_option_ids')
                ->label('Дополнительные работы')
                ->multiple()
                ->options(fn (Get $get): array => self::optionsForService($get->integer('cleaning_service_id', true), 'extra'))
                ->visibleOn('create'),
            TextInput::make('address')->label('Адрес')->required()->maxLength(255),
            TextInput::make('entrance')->label('Подъезд')->maxLength(50)->visibleOn('create'),
            TextInput::make('floor')->label('Этаж')->maxLength(50)->visibleOn('create'),
            TextInput::make('apartment')->label('Квартира или помещение')->maxLength(50)->visibleOn('create'),
            TextInput::make('intercom')->label('Домофон')->maxLength(50)->visibleOn('create'),
            DateTimePicker::make('scheduled_at')->label('Дата и время уборки')->required(),
            Textarea::make('comment')->label('Комментарий')->columnSpanFull(),
            TextInput::make('total_price')->label('Итоговая стоимость')->integer()->minValue(0)->disabled()->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_id')->label('ID')->searchable()->sortable(),
            TextColumn::make('status')
                ->label('Статус')
                ->badge()
                ->sortable(),
            TextColumn::make('service.name')->label('Услуга')->searchable(),
            TextColumn::make('client.phone')->label('Клиент')->searchable(),
            TextColumn::make('cleaners.phone')->label('Клинеры')->listWithLineBreaks(),
            TextColumn::make('cleaners_count')
                ->label('Команда')
                ->counts('cleaners')
                ->badge()
                ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray'),
            TextColumn::make('total_price')->label('Итоговая стоимость')->money('RUB')->sortable(),
            TextColumn::make('scheduled_at')->label('Дата и время уборки')->dateTime()->sortable(),
        ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(OrderStatus::class),
                SelectFilter::make('service')->label('Услуга')->relationship('service', 'name'),
                Filter::make('scheduled_at')
                    ->label('Период уборки')
                    ->schema([
                        DatePicker::make('from')->label('С'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_at', '<=', $date))),
                SelectFilter::make('cleaner')
                    ->label('Клинер')
                    ->options(fn (): array => User::query()
                        ->where('role', UserRole::Cleaner)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $cleaner): array => [$cleaner->id => $cleaner->name ?: $cleaner->phone])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $query, int|string $cleanerId): Builder => $query
                            ->whereHas('cleaners', fn (Builder $cleaners): Builder => $cleaners->whereKey($cleanerId)))),
                TernaryFilter::make('without_cleaners')
                    ->label('Без назначенных клинеров')
                    ->trueLabel('Только без клинеров')
                    ->falseLabel('Только с клинерами')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereDoesntHave('cleaners'),
                        false: fn (Builder $query): Builder => $query->whereHas('cleaners'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->recordUrl(fn (CleaningOrder $record): string => self::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (CleaningOrder $record): ?string => $record->cleaners_count === 0 ? 'bg-danger-50 dark:bg-danger-950/20' : null);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Реквизиты заказа')->columns(3)->schema([
                TextEntry::make('public_id')->label('Номер заказа')->copyable(),
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('created_at')->label('Создан')->dateTime(),
                TextEntry::make('scheduled_at')->label('Дата и время уборки')->dateTime(),
                TextEntry::make('total_price')->label('Итоговая стоимость')->money('RUB'),
                TextEntry::make('comment')->label('Комментарий')->placeholder('Не указан')->columnSpanFull(),
            ]),
            Section::make('Клиент')->columns(2)->schema([
                TextEntry::make('client.name')->label('Имя')->placeholder('Не указано'),
                TextEntry::make('client.phone')->label('Телефон')->placeholder('Не указан')
                    ->url(fn (CleaningOrder $record): string => UserResource::getUrl('view', ['record' => $record->client])),
            ]),
            Section::make('Адрес')->columns(3)->schema([
                TextEntry::make('addressSnapshot.full_address')->label('Полный адрес')
                    ->state(fn (CleaningOrder $record): string => $record->addressSnapshot?->full_address ?: $record->address)
                    ->columnSpanFull(),
                TextEntry::make('addressSnapshot.entrance')->label('Подъезд')->placeholder('—'),
                TextEntry::make('addressSnapshot.floor')->label('Этаж')->placeholder('—'),
                TextEntry::make('addressSnapshot.apartment')->label('Квартира / помещение')->placeholder('—'),
                TextEntry::make('addressSnapshot.intercom')->label('Домофон')->placeholder('—'),
                TextEntry::make('addressSnapshot.comment')->label('Комментарий к адресу')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Состав и суммы')->schema([
                RepeatableEntry::make('lineItems')->hiddenLabel()->placeholder('Состав заказа не зафиксирован')
                    ->table([
                        TableColumn::make('Вид'),
                        TableColumn::make('Позиция'),
                        TableColumn::make('Сумма'),
                        TableColumn::make('Заработок команды'),
                    ])
                    ->schema([
                        TextEntry::make('kind')->label('Вид')->formatStateUsing(fn (string $state): string => self::lineItemKindLabel($state)),
                        TextEntry::make('title')->label('Позиция'),
                        TextEntry::make('amount')->label('Сумма')->money('RUB'),
                        TextEntry::make('cleaner_earnings')->label('Заработок команды')->money('RUB')->placeholder('—'),
                    ]),
            ]),
            Section::make('Команда')->schema([
                RepeatableEntry::make('cleaners')->hiddenLabel()->placeholder('Клинеры ещё не назначены')
                    ->table([
                        TableColumn::make('Клинер'),
                        TableColumn::make('Телефон'),
                        TableColumn::make('Принял'),
                        TableColumn::make('Начал'),
                        TableColumn::make('Завершил'),
                    ])
                    ->schema([
                        TextEntry::make('name')->label('Клинер')->placeholder('Не указано'),
                        TextEntry::make('phone')->label('Телефон'),
                        TextEntry::make('pivot.accepted_at')->label('Принял')->dateTime()->placeholder('—'),
                        TextEntry::make('pivot.started_at')->label('Начал')->dateTime()->placeholder('—'),
                        TextEntry::make('pivot.completed_at')->label('Завершил')->dateTime()->placeholder('—'),
                    ]),
            ]),
            Section::make('Чек-лист')->schema([
                RepeatableEntry::make('checklist')
                    ->hiddenLabel()
                    ->state(fn (CleaningOrder $record): array => self::checklistState($record))
                    ->placeholder('Для услуги не задан чек-лист')
                    ->table([
                        TableColumn::make('Тип'),
                        TableColumn::make('Пункт'),
                        TableColumn::make('Состояние'),
                        TableColumn::make('Выполнил'),
                        TableColumn::make('Время'),
                    ])
                    ->schema([
                        TextEntry::make('kind')->label('Тип'),
                        TextEntry::make('title')->label('Пункт'),
                        TextEntry::make('status')->label('Состояние')->badge()->color(fn (string $state): string => $state === 'Выполнен' ? 'success' : 'gray'),
                        TextEntry::make('completed_by')->label('Выполнил')->placeholder('—'),
                        TextEntry::make('completed_at')->label('Время')->dateTime()->placeholder('—'),
                    ]),
            ]),
            Section::make('Оплаты')->schema([
                RepeatableEntry::make('paymentAttempts')->hiddenLabel()->placeholder('Попыток оплаты нет')
                    ->table([
                        TableColumn::make('Провайдер'),
                        TableColumn::make('Сумма'),
                        TableColumn::make('Статус'),
                        TableColumn::make('Создана'),
                        TableColumn::make('Подтверждена'),
                        TableColumn::make('Ошибка'),
                    ])
                    ->schema([
                        TextEntry::make('provider')->label('Провайдер'),
                        TextEntry::make('amount')->label('Сумма')->money('RUB'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('created_at')->label('Создана')->dateTime(),
                        TextEntry::make('confirmed_at')->label('Подтверждена')->dateTime()->placeholder('—'),
                        TextEntry::make('error_message')->label('Ошибка')
                            ->formatStateUsing(fn (?string $state, $record): ?string => filled($record->error_code) ? "{$record->error_code}: {$state}" : $state)
                            ->placeholder('—'),
                    ]),
            ]),
            Section::make('Жалобы')->schema([
                TextEntry::make('complaints_placeholder')->hiddenLabel()
                    ->state('Жалоб нет. Раздел начнёт отображать обращения после подключения модуля ADM-06.'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            CleanersRelationManager::class,
            LineItemsRelationManager::class,
            ChecklistRelationManager::class,
            PaymentAttemptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCleaningOrders::route('/'),
            'create' => CreateCleaningOrder::route('/create'),
            'view' => ViewCleaningOrder::route('/{record}'),
            'edit' => EditCleaningOrder::route('/{record}/edit'),
        ];
    }

    private static function lineItemKindLabel(string $kind): string
    {
        return match ($kind) {
            'base' => 'Основная услуга',
            'room_option' => 'Размер квартиры',
            'cleaning_option' => 'Вариант уборки',
            'extra_option' => 'Дополнительная работа',
            default => $kind,
        };
    }

    /** @return array<int, array<string, mixed>> */
    private static function checklistState(CleaningOrder $order): array
    {
        $order->loadMissing([
            'service.checklistItems',
            'checklistItems.completedBy',
            'lineItems.extraChecklistItem.completedBy',
        ]);

        $completedBase = $order->checklistItems->keyBy('service_checklist_item_id');
        $baseItems = $order->service->checklistItems->map(function ($item) use ($completedBase): array {
            $completion = $completedBase->get($item->id);

            return [
                'kind' => 'Основной',
                'title' => $item->title,
                'status' => $completion?->completed_at ? 'Выполнен' : 'Не выполнен',
                'completed_by' => $completion?->completedBy?->name ?? $completion?->completedBy?->phone,
                'completed_at' => $completion?->completed_at,
            ];
        });

        $extraItems = $order->lineItems
            ->where('kind', 'extra_option')
            ->map(function ($item): array {
                $completion = $item->extraChecklistItem;

                return [
                    'kind' => 'Доп. работа',
                    'title' => $item->title,
                    'status' => $completion?->completed_at ? 'Выполнен' : 'Не выполнен',
                    'completed_by' => $completion?->completedBy?->name ?? $completion?->completedBy?->phone,
                    'completed_at' => $completion?->completed_at,
                ];
            });

        return $baseItems->concat($extraItems)->values()->all();
    }

    /** @return array<string, string> */
    private static function optionsForService(?int $serviceId, string $group): array
    {
        if ($serviceId === null) {
            return [];
        }

        return ServiceOption::query()
            ->where('cleaning_service_id', $serviceId)
            ->where('group', $group)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('title', 'code')
            ->all();
    }
}
