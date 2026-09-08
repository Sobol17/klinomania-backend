<?php

namespace App\Filament\Resources\CleaningOrders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAttempts';

    protected static ?string $title = 'Попытки оплаты';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')->label('Провайдер'),
                TextColumn::make('external_order_id')->label('ID заказа провайдера')->copyable(),
                TextColumn::make('provider_payment_id')->label('ID платежа')->placeholder('—')->copyable(),
                TextColumn::make('amount')->label('Сумма')->money('RUB'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('provider_status')->label('Статус провайдера')->placeholder('—'),
                TextColumn::make('error_code')->label('Код ошибки')->placeholder('—'),
                TextColumn::make('error_message')->label('Ошибка')->placeholder('—')->wrap(),
                TextColumn::make('created_at')->label('Создана')->dateTime()->sortable(),
                TextColumn::make('confirmed_at')->label('Подтверждена')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Попыток оплаты нет');
    }
}
