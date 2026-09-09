<?php

namespace App\Filament\Resources\PaymentAttempts;

use App\Enums\PaymentStatus;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Filament\Resources\PaymentAttempts\Pages\CreatePaymentAttempt;
use App\Filament\Resources\PaymentAttempts\Pages\EditPaymentAttempt;
use App\Filament\Resources\PaymentAttempts\Pages\ListPaymentAttempts;
use App\Filament\Resources\PaymentAttempts\Pages\ViewPaymentAttempt;
use App\Models\PaymentAttempt;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentAttemptResource extends Resource
{
    protected static ?string $model = PaymentAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Оплаты';

    protected static ?string $modelLabel = 'оплату';

    protected static ?string $pluralModelLabel = 'оплаты';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['order.client.clientProfile']))
            ->columns([
                TextColumn::make('order.public_id')
                    ->label('Заказ')
                    ->searchable()
                    ->copyable()
                    ->url(fn (PaymentAttempt $record): string => CleaningOrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('order.client.name')
                    ->label('Клиент')
                    ->formatStateUsing(fn (?string $state, PaymentAttempt $record): string => $record->order->client->clientProfile?->name ?: $state ?: $record->order->client->phone ?: 'Не указано')
                    ->searchable(['name', 'phone']),
                TextColumn::make('amount')
                    ->label('Сумма')
                    ->money(fn (PaymentAttempt $record): string => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('status')->label('Статус')->badge()->sortable(),
                TextColumn::make('provider')
                    ->label('Провайдер')
                    ->formatStateUsing(fn (string $state): string => self::providerLabel($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('external_order_id')->label('ID операции')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provider_payment_id')->label('ID провайдера')->searchable()->copyable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Создана')->dateTime()->sortable(),
                TextColumn::make('confirmed_at')->label('Подтверждена')->dateTime()->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(PaymentStatus::class),
                SelectFilter::make('provider')
                    ->label('Провайдер')
                    ->options(fn (): array => PaymentAttempt::query()->distinct()->orderBy('provider')->pluck('provider', 'provider')->map(fn (string $provider): string => self::providerLabel($provider))->all()),
                self::dateFilter('created_at', 'Период создания'),
                self::dateFilter('confirmed_at', 'Период подтверждения'),
                TernaryFilter::make('failed')
                    ->label('Ошибочные оплаты')
                    ->trueLabel('Только с ошибкой')
                    ->falseLabel('Без ошибок')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('status', PaymentStatus::Failed),
                        false: fn (Builder $query): Builder => $query->where('status', '!=', PaymentStatus::Failed),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([ViewAction::make()->label('Подробнее')])
            ->recordUrl(fn (PaymentAttempt $record): string => self::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (PaymentAttempt $record): ?string => match (true) {
                $record->status === PaymentStatus::Failed => 'bg-danger-50 dark:bg-danger-950/20',
                $record->status === PaymentStatus::Pending && $record->expires_at?->isPast() => 'bg-warning-50 dark:bg-warning-950/20',
                default => null,
            })
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Оплат пока нет')
            ->emptyStateDescription('Попытки оплаты появятся здесь после завершения уборки клиентом.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Оплата')->columns(3)->schema([
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('amount')->label('Сумма')->money(fn (PaymentAttempt $record): string => $record->currency, divideBy: 100),
                TextEntry::make('created_at')->label('Создана')->dateTime(),
                TextEntry::make('order.public_id')
                    ->label('Заказ')
                    ->copyable()
                    ->url(fn (PaymentAttempt $record): string => CleaningOrderResource::getUrl('view', ['record' => $record->order])),
                TextEntry::make('confirmed_at')->label('Подтверждена')->dateTime()->placeholder('—'),
                TextEntry::make('expires_at')
                    ->label('Ссылка действует до')
                    ->dateTime()
                    ->placeholder('Срок не задан')
                    ->color(fn (PaymentAttempt $record): string => $record->status === PaymentStatus::Pending && $record->expires_at?->isPast() ? 'danger' : 'gray'),
            ]),
            Section::make('Реквизиты T-Bank')->columns(2)->schema([
                TextEntry::make('provider')->label('Провайдер')->formatStateUsing(fn (string $state): string => self::providerLabel($state))->badge()->color('gray'),
                TextEntry::make('provider_status')->label('Статус провайдера')->badge()->placeholder('Не получен'),
                TextEntry::make('external_order_id')->label('ID операции')->copyable(),
                TextEntry::make('provider_payment_id')->label('ID платежа провайдера')->copyable()->placeholder('Не присвоен'),
                TextEntry::make('payment_url')
                    ->label('Платёжная ссылка')
                    ->state(fn (PaymentAttempt $record): string => filled($record->payment_url) ? 'Создана и скрыта' : 'Не создана')
                    ->icon(fn (PaymentAttempt $record): string => filled($record->payment_url) ? 'heroicon-m-lock-closed' : 'heroicon-m-minus-circle')
                    ->color('gray')
                    ->helperText('Действующая ссылка не отображается в панели из соображений безопасности.')
                    ->columnSpanFull(),
            ]),
            Section::make('Диагностика')
                ->icon('heroicon-o-exclamation-circle')
                ->visible(fn (PaymentAttempt $record): bool => $record->status === PaymentStatus::Failed || filled($record->error_code) || filled($record->error_message))
                ->columns(2)
                ->schema([
                    TextEntry::make('error_code')->label('Код ошибки')->copyable()->placeholder('Не указан'),
                    TextEntry::make('error_message')->label('Сообщение провайдера')->placeholder('Не указано')->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentAttempts::route('/'),
            'create' => CreatePaymentAttempt::route('/create'),
            'view' => ViewPaymentAttempt::route('/{record}'),
            'edit' => EditPaymentAttempt::route('/{record}/edit'),
        ];
    }

    private static function dateFilter(string $field, string $label): Filter
    {
        return Filter::make($field)
            ->label($label)
            ->schema([
                DatePicker::make('from')->label('С'),
                DatePicker::make('until')->label('По'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($field, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($field, '<=', $date)));
    }

    private static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'tbank' => 'T-Bank',
            default => $provider,
        };
    }
}
