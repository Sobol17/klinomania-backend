<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'История отметок чек-листа';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('checklistItem.title')->label('Работа')->wrap(),
            TextColumn::make('checklistItem.zone')->label('Зона')->badge(),
            TextColumn::make('completedBy.name')->label('Отметил')->placeholder('Пользователь не указан'),
            TextColumn::make('completed_at')->label('Время отметки')->dateTime()->placeholder('Отметка снята'),
        ])->emptyStateHeading('История пока пуста')
            ->emptyStateDescription('Отметки появятся здесь, когда клинер начнёт выполнять чек-лист.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
