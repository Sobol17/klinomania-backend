<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ComplaintsRelationManager extends RelationManager
{
    protected static string $relationship = 'complaints';

    protected static ?string $title = 'Жалобы';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label('Тема')->searchable(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('created_at')->label('Дата')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('open')->label('Открыть жалобу')
                    ->url(fn (Model $record): string => ComplaintResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Жалоб нет');
    }
}
