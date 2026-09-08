<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';

    protected static ?string $title = 'Состав заказа';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('kind')->label('Вид')->badge(),
            TextColumn::make('title')->label('Позиция'),
            TextColumn::make('source_option_id')->label('Код опции')->placeholder('—'),
            TextColumn::make('amount')->label('Сумма')->money('RUB'),
            TextColumn::make('cleaner_earnings')->label('Заработок команды')->money('RUB')->placeholder('—'),
        ]);
    }
}
