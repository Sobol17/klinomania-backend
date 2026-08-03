<?php

namespace App\Modules\Notifications\Notifications;

use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Models\CleaningOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = CleaningOrder::query()
            ->with(['client.clientProfile', 'service'])
            ->findOrFail($this->orderId);

        $clientName = $order->client->clientProfile?->name
            ?: $order->client->name
            ?: 'Не указано';
        $clientPhone = $order->client->phone ?: 'Не указан';
        $timezone = (string) config('app.timezone');
        $scheduledAt = $order->scheduled_at
            ->copy()
            ->timezone($timezone)
            ->format('d.m.Y H:i');
        $totalPrice = number_format($order->total_price, 0, ',', ' ').' ₽';

        return (new MailMessage)
            ->subject("Новая заявка №{$order->public_id}")
            ->greeting('Поступила новая заявка')
            ->line("**ID заявки:** {$order->public_id}")
            ->line('**Статус:** В обработке')
            ->line("**Клиент:** {$clientName}")
            ->line("**Телефон:** {$clientPhone}")
            ->line("**Услуга:** {$order->service->name}")
            ->line("**Дата и время:** {$scheduledAt} ({$timezone})")
            ->line("**Адрес:** {$order->address}")
            ->lineIf(filled($order->comment), "**Комментарий:** {$order->comment}")
            ->line("**Итоговая сумма:** {$totalPrice}")
            ->action(
                'Открыть заявку',
                CleaningOrderResource::getUrl('edit', ['record' => $order]),
            )
            ->salutation('Клиномания');
    }
}
