<?php

namespace App\Filament\Resources\CleaningServices;

use App\Enums\ChecklistZone;
use App\Filament\Resources\CleaningServices\Pages\CreateCleaningService;
use App\Filament\Resources\CleaningServices\Pages\EditCleaningService;
use App\Filament\Resources\CleaningServices\Pages\ListCleaningServices;
use App\Models\CleaningService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CleaningServiceResource extends Resource
{
    protected static ?string $model = CleaningService::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Услуги уборки';

    protected static ?string $modelLabel = 'услугу уборки';

    protected static ?string $pluralModelLabel = 'услуги уборки';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            Textarea::make('description')->label('Описание')->columnSpanFull(),
            TextInput::make('base_price')->label('Базовая стоимость')->required()->integer()->minValue(0),
            TextInput::make('cleaner_base_earnings')->label('Выручка команды клинеров')->required()->integer()->minValue(0)->default(0),
            TextInput::make('required_cleaners')->label('Требуемое количество клинеров')->required()->integer()->minValue(1)->default(1),
            Tabs::make('Чеклист по зонам')->tabs([
                Tab::make(ChecklistZone::Everywhere->label())->schema([self::checklistRepeater(ChecklistZone::Everywhere)]),
                Tab::make(ChecklistZone::Rooms->label())->schema([self::checklistRepeater(ChecklistZone::Rooms)]),
                Tab::make(ChecklistZone::Kitchen->label())->schema([self::checklistRepeater(ChecklistZone::Kitchen)]),
                Tab::make(ChecklistZone::Bathroom->label())->schema([self::checklistRepeater(ChecklistZone::Bathroom)]),
            ])->columnSpanFull(),
            Toggle::make('is_active')->label('Активна')->default(true),
        ]);
    }

    private static function checklistRepeater(ChecklistZone $zone): Repeater
    {
        return Repeater::make('checklistItems_'.$zone->value)
            ->label('Работы в зоне')
            ->relationship('checklistItems', fn ($query) => $query->where('zone', $zone->value))
            ->table([
                TableColumn::make('Пункт работы')->markAsRequired(),
            ])
            ->schema([
                Hidden::make('zone')->default($zone->value),
                TextInput::make('title')
                    ->label('Пункт работы')
                    ->placeholder('Например, протираем пыль')
                    ->required()
                    ->maxLength(255),
            ])
            ->orderColumn('sort_order')
            ->reorderableWithDragAndDrop()
            ->compact()
            ->addActionLabel('Добавить работу')
            ->defaultItems(0)
            ->extraAttributes(['class' => 'service-checklist-repeater']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Название')->searchable()->sortable(),
            TextColumn::make('base_price')->label('Базовая стоимость')->money('RUB')->sortable(),
            TextColumn::make('cleaner_base_earnings')->label('Выручка клинеров')->money('RUB')->sortable(),
            TextColumn::make('required_cleaners')->label('Клинеры')->sortable(),
            IconColumn::make('is_active')->label('Активна')->boolean(),
            TextColumn::make('created_at')->label('Создано')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCleaningServices::route('/'),
            'create' => CreateCleaningService::route('/create'),
            'edit' => EditCleaningService::route('/{record}/edit'),
        ];
    }
}
