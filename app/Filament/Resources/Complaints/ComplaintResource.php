<?php

namespace App\Filament\Resources\Complaints;

use App\Enums\ComplaintStatus;
use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Filament\Resources\Complaints\Pages\EditComplaint;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Resources\Users\UserResource;
use App\Models\Complaint;
use App\Modules\Complaints\Actions\ChangeComplaintStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Жалобы';

    protected static ?string $modelLabel = 'жалобу';

    protected static ?string $pluralModelLabel = 'жалобы';

    public static function getNavigationBadge(): ?string
    {
        return (string) Complaint::query()
            ->whereIn('status', [ComplaintStatus::New, ComplaintStatus::InProgress])
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subject')->label('Тема')->disabled()->dehydrated(false),
            Textarea::make('message')->label('Сообщение клиента')->rows(6)->disabled()->dehydrated(false)->columnSpanFull(),
            Select::make('status')->label('Статус')->options(ComplaintStatus::class)->required(),
            Textarea::make('admin_comment')->label('Комментарий администратора')->rows(5)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['order', 'client.clientProfile']))
            ->columns([
                TextColumn::make('order.public_id')->label('Номер заказа')->searchable()->sortable(),
                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->formatStateUsing(fn (?string $state, Complaint $record): string => $record->client->clientProfile?->name ?: $state ?: $record->client->phone ?: 'Не указано')
                    ->searchable(['name', 'phone']),
                TextColumn::make('subject')->label('Тема')->searchable()->limit(60),
                TextColumn::make('status')->label('Статус')->badge()->sortable(),
                TextColumn::make('created_at')->label('Дата')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(ComplaintStatus::class),
                Filter::make('created_at')
                    ->label('Период')
                    ->schema([
                        DatePicker::make('from')->label('С'),
                        DatePicker::make('until')->label('По'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::statusAction('take', 'Взять в работу', ComplaintStatus::InProgress, 'warning'),
                self::statusAction('resolve', 'Решена', ComplaintStatus::Resolved, 'success', true),
                self::statusAction('reject', 'Отклонена', ComplaintStatus::Rejected, 'danger', true),
            ])
            ->recordUrl(fn (Complaint $record): string => self::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Жалоба')->columns(3)->schema([
                TextEntry::make('order.public_id')->label('Номер заказа')->url(fn (Complaint $record): string => CleaningOrderResource::getUrl('view', ['record' => $record->order])),
                TextEntry::make('status')->label('Статус')->badge(),
                TextEntry::make('created_at')->label('Создана')->dateTime(),
                TextEntry::make('subject')->label('Тема')->columnSpanFull(),
                TextEntry::make('message')->label('Сообщение клиента')->columnSpanFull(),
            ]),
            Section::make('Клиент')->columns(2)->schema([
                TextEntry::make('client.name')->label('Имя')->placeholder('Не указано'),
                TextEntry::make('client.phone')->label('Телефон')->url(fn (Complaint $record): string => UserResource::getUrl('view', ['record' => $record->client])),
            ]),
            Section::make('Обработка')->columns(2)->schema([
                TextEntry::make('admin_comment')->label('Комментарий администратора')->placeholder('—')->columnSpanFull(),
                TextEntry::make('resolvedBy.name')->label('Обработал')->placeholder('—'),
                TextEntry::make('resolved_at')->label('Дата обработки')->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function statusAction(string $name, string $label, ComplaintStatus $status, string $color, bool $commentRequired = false): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->visible(fn (Complaint $record): bool => $record->status !== $status)
            ->schema($commentRequired ? [
                Textarea::make('admin_comment')->label('Комментарий администратора')->required()->maxLength(5000),
            ] : [])
            ->action(function (Complaint $record, array $data, ChangeComplaintStatus $changeStatus) use ($status): void {
                $changeStatus->execute($record, $status, auth()->user(), $data['admin_comment'] ?? null);
                Notification::make()->title('Статус жалобы обновлён')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'view' => ViewComplaint::route('/{record}'),
            'edit' => EditComplaint::route('/{record}/edit'),
        ];
    }
}
