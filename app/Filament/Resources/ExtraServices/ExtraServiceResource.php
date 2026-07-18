<?php

namespace App\Filament\Resources\ExtraServices;

use App\Filament\Resources\ExtraServices\Pages\CreateExtraService;
use App\Filament\Resources\ExtraServices\Pages\EditExtraService;
use App\Filament\Resources\ExtraServices\Pages\ListExtraServices;
use App\Models\ServiceOption;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExtraServiceResource extends Resource
{
    protected static ?string $model = ServiceOption::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Дополнительные услуги';

    protected static ?string $modelLabel = 'дополнительную услугу';

    protected static ?string $pluralModelLabel = 'дополнительные услуги';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('group', 'extra');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('group')->default('extra'),
            Hidden::make('is_addon')->default(true),
            Select::make('cleaning_service_id')->label('Основная услуга')->relationship('service', 'name')->required(),
            TextInput::make('code')->label('Код')->required()->maxLength(255),
            TextInput::make('title')->label('Название')->required()->maxLength(255),
            TextInput::make('price_modifier')->label('Цена')->required()->integer()->minValue(0),
            TextInput::make('cleaner_revenue_percent')->label('Процент выручки клинера')->required()->integer()->minValue(0)->maxValue(100)->default(0),
            TextInput::make('sort_order')->label('Порядок')->required()->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Активна')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('service.name')->label('Основная услуга')->searchable()->sortable(),
            TextColumn::make('title')->label('Название')->searchable()->sortable(),
            TextColumn::make('price_modifier')->label('Цена')->money('RUB')->sortable(),
            TextColumn::make('cleaner_revenue_percent')->label('Клинер, %')->sortable(),
            TextColumn::make('sort_order')->label('Порядок')->sortable(),
            IconColumn::make('is_active')->label('Активна')->boolean(),
        ])->defaultSort('sort_order')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtraServices::route('/'),
            'create' => CreateExtraService::route('/create'),
            'edit' => EditExtraService::route('/{record}/edit'),
        ];
    }
}
