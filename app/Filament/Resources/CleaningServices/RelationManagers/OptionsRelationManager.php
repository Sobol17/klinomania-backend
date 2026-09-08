<?php

namespace App\Filament\Resources\CleaningServices\RelationManagers;

use App\Enums\ChecklistZone;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Опции';

    public function getTabs(): array
    {
        return [
            'room' => Tab::make('Размер квартиры')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('group', 'room')),
            'cleaning' => Tab::make('Основной вариант уборки')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('group', 'cleaning')),
            'extra' => Tab::make('Дополнительные опции')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('group', 'extra')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Отображение в мобильном приложении')->columns(2)->schema([
                Select::make('group')->label('Блок формы')->options([
                    'room' => 'Размер квартиры',
                    'cleaning' => 'Основной вариант уборки',
                    'extra' => 'Дополнительные опции',
                ])->required()->live()->default(fn (): string => in_array($this->activeTab, ['room', 'cleaning', 'extra'], true) ? $this->activeTab : 'room'),
                TextInput::make('code')->label('ID опции')->required()->alphaDash()->maxLength(255)
                    ->helperText('Для мытья окон используйте формат windows-room-1, windows-room-2 и т. д.')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('cleaning_service_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('title')->label('Название')->required()->maxLength(255)
                    ->helperText('Этот текст пользователь видит в карточке выбора.'),
                TextInput::make('price_modifier')->label('Доплата, ₽')->required()->integer()->minValue(0)->default(0),
                TextInput::make('sort_order')->label('Порядок показа')->required()->integer()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Показывать в приложении')->default(true),
            ]),
            Section::make('Внутренние параметры')->collapsed()->columns(2)->schema([
                TextInput::make('cleaner_revenue_percent')->label('Доля клинера, %')->required()->integer()->minValue(0)->maxValue(100)->default(0),
                Select::make('checklist_zone')->label('Зона чек-листа')->options(ChecklistZone::class)->required()->default(ChecklistZone::Everywhere->value),
                Select::make('allowedWith')->label('Совместима с вариантами размера')->relationship(
                    name: 'allowedWith',
                    titleAttribute: 'title',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->where('cleaning_service_id', $this->getOwnerRecord()->getKey()),
                    ignoreRecord: true,
                )->multiple()->preload()->searchable()
                    ->helperText('Для окон выберите соответствующий вариант размера квартиры. Пустое поле не ограничивает совместимость.'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Код')->searchable(),
                TextColumn::make('title')->label('Название')->searchable(),
                TextColumn::make('price_modifier')->label('Надбавка')->money('RUB')->sortable(),
                TextColumn::make('cleaner_revenue_percent')->label('Клинер, %')->sortable(),
                TextColumn::make('checklist_zone')->label('Зона')->badge(),
                IconColumn::make('is_addon')->label('Доп.')->boolean(),
                IconColumn::make('is_default')->label('По умолчанию')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
                ToggleColumn::make('is_active')->label('Активна')->disabled(fn ($record): bool => ! $this->canEdit($record)),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('Добавить опцию')->createAnother(false)
                    ->mutateDataUsing(self::normalizeOptionData(...)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(self::normalizeOptionData(...)),
                DeleteAction::make(),
            ]);
    }

    /** @param array<string, mixed> $data */
    private static function normalizeOptionData(array $data): array
    {
        $data['is_addon'] = $data['group'] === 'extra';

        return $data;
    }
}
