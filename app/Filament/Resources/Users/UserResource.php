<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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
            TextInput::make('password')->label('Пароль')->password()->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('role')->label('Роль')->options(self::roleOptions())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Имя')->searchable(),
            TextColumn::make('phone')->label('Телефон')->searchable(),
            TextColumn::make('email')->label('Электронная почта')->searchable(),
            TextColumn::make('role')->label('Роль')->badge()->formatStateUsing(fn (UserRole $state): string => self::roleLabel($state))->sortable(),
            TextColumn::make('created_at')->label('Создано')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    private static function roleOptions(): array
    {
        return collect(UserRole::cases())->mapWithKeys(
            fn (UserRole $role): array => [$role->value => self::roleLabel($role)]
        )->all();
    }

    private static function roleLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::Client => 'Клиент',
            UserRole::Cleaner => 'Клинер',
            UserRole::Admin => 'Администратор',
        };
    }
}
