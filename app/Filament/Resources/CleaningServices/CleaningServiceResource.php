<?php

namespace App\Filament\Resources\CleaningServices;

use App\Enums\ChecklistZone;
use App\Filament\Resources\CleaningServices\Pages\CreateCleaningService;
use App\Filament\Resources\CleaningServices\Pages\EditCleaningService;
use App\Filament\Resources\CleaningServices\Pages\ListCleaningServices;
use App\Filament\Resources\CleaningServices\RelationManagers\OptionsRelationManager;
use App\Models\CleaningService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            Section::make('Карточка в мобильном приложении')->columns(2)->schema([
                TextInput::make('name')->label('Название')->required()->maxLength(255)
                    ->helperText('Заголовок карточки и детального экрана.'),
                TextInput::make('slug')->label('ID услуги')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true)
                    ->helperText('Передаётся приложением при оформлении заказа. После публикации не менять.'),
                TextInput::make('subtitle')->label('Подзаголовок')->maxLength(255),
                TextInput::make('cleaners_label')->label('Подпись о клинерах')->maxLength(255)
                    ->placeholder('Например, 1–2 клинера'),
                TextInput::make('duration_label')->label('Продолжительность')->maxLength(255)
                    ->placeholder('Например, 2–3 часа'),
            ]),
            Section::make('Описание')->schema([
                Textarea::make('long_description')->label('Описание услуги')->rows(6)->columnSpanFull()
                    ->helperText('Показывается под обложкой на детальном экране услуги.'),
            ]),
            Section::make('Стоимость')->schema([
                TextInput::make('base_price')->label('Стоимость услуги, ₽')->required()->integer()->minValue(0)
                    ->helperText('В приложении показывается как цена «от». К ней добавляются выбранные опции.'),
            ]),
            Section::make('Внутренние параметры')->columns(2)->collapsed()->schema([
                TextInput::make('cleaner_base_earnings')->label('Выручка команды клинеров')->required()->integer()->minValue(0)->default(0),
                TextInput::make('required_cleaners')->label('Требуемое количество клинеров')->required()->integer()->minValue(1)->default(1),
                TextInput::make('sort_order')->label('Порядок в каталоге')->required()->integer()->minValue(0)->default(0),
                Toggle::make('is_active')->label('Показывать в приложении')->default(true),
            ]),
            Section::make('Изображения в приложении')->columns(2)->schema([
                self::imageUpload('image_url', 'Обложка услуги')
                    ->helperText('Показывается в верхней части детального экрана.'),
                self::imageUpload('gallery', 'Галерея')->multiple()->reorderable()
                    ->helperText('Порядок изображений сохраняется для мобильного приложения.'),
            ]),
            Section::make('Маршрут уборки')
                ->description('Соберите понятный маршрут по помещениям — именно так его увидит клинер в приложении.')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Callout::make('Изменения применяются к текущим заказам')
                        ->description('После сохранения обновлённый список применяется ко всем заказам этой услуги, включая созданные ранее. Удаление пункта также удалит связанную историю его отметок.')
                        ->warning(),
                    Tabs::make('Чек-лист по зонам')->tabs([
                        self::checklistTab(ChecklistZone::Everywhere),
                        self::checklistTab(ChecklistZone::Rooms),
                        self::checklistTab(ChecklistZone::Kitchen),
                        self::checklistTab(ChecklistZone::Bathroom),
                    ])->columnSpanFull(),
                ]),
        ]);
    }

    private static function imageUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('public')
            ->directory('services')
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120)
            ->fetchFileInformation(false)
            ->preventFilePathTampering(true, fn (string $file): bool => Str::startsWith($file, 'services/') && Storage::disk('public')->exists($file))
            ->afterStateHydrated(function (FileUpload $component, mixed $state): void {
                $paths = array_values(array_filter(array_map(self::publicStoragePath(...), Arr::wrap($state))));
                $component->state($component->isMultiple() ? $paths : Arr::first($paths));
            })
            ->mutateDehydratedStateUsing(function (FileUpload $component, mixed $state): string|array|null {
                $urls = array_values(array_filter(array_map(self::publicStorageUrl(...), Arr::wrap($state))));

                return $component->isMultiple() ? $urls : Arr::first($urls);
            })
            ->deleteUploadedFileUsing(fn (string $file): bool => Storage::disk('public')->delete($file));
    }

    public static function publicStoragePath(mixed $url): ?string
    {
        if (! is_string($url) || blank($url)) {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return Str::startsWith($url, 'services/') ? $url : null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && Str::startsWith($path, '/storage/services/')
            ? Str::after($path, '/storage/')
            : null;
    }

    public static function publicStorageUrl(mixed $path): ?string
    {
        if (! is_string($path) || blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return rtrim((string) config('filesystems.disks.public.url'), '/').'/'.ltrim($path, '/');
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
            ->reorderableWithButtons()
            ->compact()
            ->addActionLabel('Добавить пункт в эту зону')
            ->defaultItems(0)
            ->deleteAction(fn (Action $action): Action => $action
                ->requiresConfirmation()
                ->modalHeading('Удалить пункт чек-листа?')
                ->modalDescription('После сохранения пункт исчезнет из всех заказов этой услуги, а связанная история отметок будет удалена.'))
            ->extraAttributes(['class' => 'service-checklist-repeater']);
    }

    private static function checklistTab(ChecklistZone $zone): Tab
    {
        return Tab::make($zone->getLabel())
            ->icon(self::checklistZoneIcon($zone))
            ->badge(fn (Get $get): int => count($get('checklistItems_'.$zone->value) ?? []))
            ->badgeColor('primary')
            ->schema([self::checklistRepeater($zone)]);
    }

    private static function checklistZoneIcon(ChecklistZone $zone): string
    {
        return match ($zone) {
            ChecklistZone::Everywhere => 'heroicon-o-arrows-pointing-out',
            ChecklistZone::Rooms => 'heroicon-o-home-modern',
            ChecklistZone::Kitchen => 'heroicon-o-fire',
            ChecklistZone::Bathroom => 'heroicon-o-sparkles',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')->label('Изображение')->square()->imageSize(48),
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('base_price')->label('Стоимость')->money('RUB')->sortable(),
                TextColumn::make('options_count')->label('Опции')->counts('options')->sortable(),
                TextColumn::make('required_cleaners')->label('Клинеры')->sortable(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
                ToggleColumn::make('is_active')->label('Активна')->disabled(fn (CleaningService $record): bool => ! static::canEdit($record)),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активность')->trueLabel('Только активные')->falseLabel('Только неактивные'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                static::deleteAction(),
            ]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->using(function (CleaningService $record): bool {
            if ($record->orders()->exists()) {
                Notification::make()
                    ->title('Услугу нельзя удалить')
                    ->body('С услугой связаны заказы. Деактивируйте её, чтобы скрыть из каталога.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw new Halt;
            }

            return (bool) $record->delete();
        });
    }

    public static function getRelations(): array
    {
        return [OptionsRelationManager::class];
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
