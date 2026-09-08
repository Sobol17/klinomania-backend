<?php

namespace App\Filament\Resources\ExtraServices;

use App\Enums\ChecklistZone;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

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
            Hidden::make('group')->default('extra')->dehydrateStateUsing(fn (): string => 'extra'),
            Hidden::make('is_addon')->default(true)->dehydrateStateUsing(fn (): bool => true),
            Section::make('Отображение в мобильном приложении')->columns(2)->schema([
                Select::make('cleaning_service_id')->label('Услуга')->relationship('service', 'name')->required()->live(),
                TextInput::make('code')->label('ID опции')->required()->alphaDash()->maxLength(255)
                    ->helperText('Для мытья окон используйте формат windows-room-1, windows-room-2 и т. д.')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('cleaning_service_id', $get('cleaning_service_id'))),
                TextInput::make('title')->label('Название')->required()->maxLength(255),
                TextInput::make('price_modifier')->label('Доплата, ₽')->required()->integer()->minValue(0),
                TextInput::make('sort_order')->label('Порядок показа')->required()->integer()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Показывать в приложении')->default(true),
            ]),
            Section::make('Внутренние параметры')->collapsed()->columns(2)->schema([
                Select::make('checklist_zone')->label('Зона чек-листа')->options(ChecklistZone::class)->required()->default(ChecklistZone::Everywhere->value),
                TextInput::make('cleaner_revenue_percent')->label('Доля клинера, %')->required()->integer()->minValue(0)->maxValue(100)->default(0),
                Select::make('allowedWith')->label('Совместима с вариантами размера')->relationship(
                    name: 'allowedWith',
                    titleAttribute: 'title',
                    modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('cleaning_service_id', $get('cleaning_service_id')),
                    ignoreRecord: true,
                )->multiple()->preload()->searchable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('service.name')->label('Основная услуга')->searchable()->sortable(),
            TextColumn::make('title')->label('Название')->searchable()->sortable(),
            TextColumn::make('price_modifier')->label('Доплата')->money('RUB')->sortable(),
            TextColumn::make('cleaner_revenue_percent')->label('Клинер, %')->sortable(),
            TextColumn::make('sort_order')->label('Порядок')->sortable(),
            ToggleColumn::make('is_active')->label('Активна')->disabled(fn (ServiceOption $record): bool => ! static::canEdit($record)),
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
