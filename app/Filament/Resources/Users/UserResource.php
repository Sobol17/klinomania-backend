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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->password()->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('role')->options(self::roleOptions())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('role')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
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
            fn (UserRole $role): array => [$role->value => $role->value]
        )->all();
    }
}
