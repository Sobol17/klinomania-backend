<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'Отметки чек-листа';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('checklistItem.title')->label('Пункт'),
            TextColumn::make('checklistItem.zone')->label('Зона')->badge(),
            TextColumn::make('completedBy.name')->label('Выполнил')->placeholder('—'),
            TextColumn::make('completed_at')->label('Выполнен')->dateTime()->placeholder('—'),
        ])->emptyStateHeading('Выполненных пунктов пока нет');
    }
}
