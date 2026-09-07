<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\UserRole;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'cleaningOrders';

    protected static UserRole $ownerRole = UserRole::Cleaner;

    protected static ?string $title = 'Заказы';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->role === static::$ownerRole && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_id')->label('Номер')->searchable(),
            TextColumn::make('status')->label('Статус')->badge(),
            TextColumn::make('address')->label('Адрес'),
            TextColumn::make('scheduled_at')->label('Дата уборки')->dateTime()->sortable(),
            TextColumn::make('total_price')->label('Стоимость')->money('RUB'),
        ])->defaultSort('scheduled_at', 'desc')->recordActions([
            Action::make('open')->label('Открыть заказ')
                ->visible(fn (Model $record): bool => CleaningOrderResource::canEdit($record))
                ->url(fn (Model $record): string => CleaningOrderResource::getUrl('edit', ['record' => $record])),
        ]);
    }
}
