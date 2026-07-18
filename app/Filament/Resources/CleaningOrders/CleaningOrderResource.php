<?php

namespace App\Filament\Resources\CleaningOrders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\Pages\CreateCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\EditCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Models\CleaningOrder;
use App\Models\ServiceOption;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                ->formatStateUsing(fn (OrderStatus $state): string => self::statusLabel($state))
                ->sortable(),
            TextColumn::make('service.name')->label('Услуга')->searchable(),
            TextColumn::make('client.phone')->label('Клиент')->searchable(),
            TextColumn::make('cleaners.phone')->label('Клинеры')->listWithLineBreaks(),
            TextColumn::make('total_price')->label('Итоговая стоимость')->money('RUB')->sortable(),
            TextColumn::make('scheduled_at')->label('Дата и время уборки')->dateTime()->sortable(),
        ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options([
                    'processing' => 'В обработке',
                    'confirmed' => 'Подтверждена',
                    'team_formed' => 'Команда сформирована',
                    'in_progress' => 'В работе',
                    'awaiting_payment' => 'Ожидает оплаты',
                    'completed' => 'Выполнена',
                    'cancelled' => 'Отменена',
                ]),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCleaningOrders::route('/'),
            'create' => CreateCleaningOrder::route('/create'),
            'edit' => EditCleaningOrder::route('/{record}/edit'),
        ];
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

    private static function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Processing => 'В обработке',
            OrderStatus::Confirmed => 'Подтверждена',
            OrderStatus::TeamFormed => 'Команда сформирована',
            OrderStatus::InProgress => 'В работе',
            OrderStatus::AwaitingPayment => 'Ожидает оплаты',
            OrderStatus::Completed => 'Выполнена',
            OrderStatus::Cancelled => 'Отменена',
        };
    }
}
