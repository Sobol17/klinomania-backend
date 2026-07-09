<?php

namespace App\Filament\Resources\CleaningOrders;

use App\Enums\OrderStatus;
use App\Filament\Resources\CleaningOrders\Pages\EditCleaningOrder;
use App\Filament\Resources\CleaningOrders\Pages\ListCleaningOrders;
use App\Models\CleaningOrder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CleaningOrderResource extends Resource
{
    protected static ?string $model = CleaningOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')->relationship('client', 'phone')->required(),
            Select::make('cleaner_id')->relationship('cleaner', 'phone'),
            Select::make('cleaning_service_id')->relationship('service', 'name')->required(),
            Select::make('status')->options(self::statusOptions())->required(),
            TextInput::make('address')->required()->maxLength(255),
            DateTimePicker::make('scheduled_at'),
            Textarea::make('comment')->columnSpanFull(),
            TextInput::make('total_price')->required()->integer()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('service.name')->searchable(),
            TextColumn::make('client.phone')->searchable(),
            TextColumn::make('cleaner.phone')->searchable(),
            TextColumn::make('total_price')->money('RUB')->sortable(),
            TextColumn::make('scheduled_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCleaningOrders::route('/'),
            'edit' => EditCleaningOrder::route('/{record}/edit'),
        ];
    }

    private static function statusOptions(): array
    {
        return collect(OrderStatus::cases())->mapWithKeys(
            fn (OrderStatus $status): array => [$status->value => $status->value]
        )->all();
    }
}
