<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ClientOrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователя';

    protected static ?string $pluralModelLabel = 'пользователи';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Имя')->maxLength(255),
            TextInput::make('phone')->label('Телефон')->tel()->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('email')->label('Электронная почта')->email()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->label('Пароль')->password()->afterStateHydrated(fn (TextInput $component) => $component->state(null))->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('role')->label('Роль')->options(UserRole::class)->required()->live()
                ->helperText('Смена роли изменяет доступ. Ранее созданные профили и история заказов сохраняются.'),
            Section::make('Профиль клиента')->relationship('clientProfile')
                ->visible(fn (Get $get): bool => in_array($get('role'), [UserRole::Client, UserRole::Client->value], true))
                ->schema([
                    TextInput::make('name')->label('Имя в профиле')->maxLength(255),
                    TextInput::make('address')->label('Адрес')->maxLength(255),
                    Toggle::make('push_notifications_enabled')->label('Push-уведомления')->default(true),
                    Toggle::make('email_marketing_enabled')->label('Рассылка по email')->default(false),
                ]),
            Section::make('Профиль клинера')->relationship('cleanerProfile')
                ->visible(fn (Get $get): bool => in_array($get('role'), [UserRole::Cleaner, UserRole::Cleaner->value], true))
                ->schema([
                    TextInput::make('name')->label('Имя в профиле')->maxLength(255),
                    Toggle::make('is_active')->label('Активен')->default(true)
                        ->helperText('Неактивный клинер не может войти по коду доступа.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Имя')->searchable(),
            TextColumn::make('phone')->label('Телефон')->searchable(),
            TextColumn::make('email')->label('Электронная почта')->searchable(),
            TextColumn::make('role')->label('Роль')->badge()->sortable(),
            IconColumn::make('cleanerProfile.is_active')->label('Клинер активен')->boolean(),
            TextColumn::make('created_at')->label('Создано')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('role')->label('Роль')->options(UserRole::class),
            TernaryFilter::make('cleaner_active')->label('Активность клинера')->queries(
                true: fn ($query) => $query->where('role', UserRole::Cleaner)->whereHas('cleanerProfile', fn ($profile) => $profile->where('is_active', true)),
                false: fn ($query) => $query->where('role', UserRole::Cleaner)->whereHas('cleanerProfile', fn ($profile) => $profile->where('is_active', false)),
                blank: fn ($query) => $query,
            ),
        ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Имя')->placeholder('Не указано'),
            TextEntry::make('phone')->label('Телефон')->placeholder('Не указан'),
            TextEntry::make('email')->label('Email')->placeholder('Не указан'),
            TextEntry::make('role')->label('Роль')->badge(),
            TextEntry::make('created_at')->label('Дата регистрации')->dateTime(),
            Section::make('Профиль клиента')->visible(fn (User $record): bool => $record->role === UserRole::Client)->schema([
                TextEntry::make('clientProfile.name')->label('Имя в профиле')->placeholder('Не указано'),
                TextEntry::make('clientProfile.address')->label('Адрес')->placeholder('Не указан'),
                IconEntry::make('clientProfile.push_notifications_enabled')->label('Push-уведомления')->boolean(),
                IconEntry::make('clientProfile.email_marketing_enabled')->label('Рассылка по email')->boolean(),
            ]),
            Section::make('Профиль клинера')->visible(fn (User $record): bool => $record->role === UserRole::Cleaner)->schema([
                TextEntry::make('cleanerProfile.name')->label('Имя в профиле')->placeholder('Не указано'),
                IconEntry::make('cleanerProfile.is_active')->label('Активен')->boolean(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ClientOrdersRelationManager::class, OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
