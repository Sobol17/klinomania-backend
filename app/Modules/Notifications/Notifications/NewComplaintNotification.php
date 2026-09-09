<?php

namespace App\Modules\Notifications\Notifications;

use App\Filament\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComplaintNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $complaintId)
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
        $complaint = Complaint::query()
            ->with(['order', 'client.clientProfile'])
            ->findOrFail($this->complaintId);
        $clientName = $complaint->client->clientProfile?->name
            ?: $complaint->client->name
            ?: $complaint->client->phone
            ?: 'Не указано';

        return (new MailMessage)
            ->subject("Новая жалоба по заявке №{$complaint->order->public_id}")
            ->greeting('Поступила новая жалоба')
            ->line("**Заявка:** {$complaint->order->public_id}")
            ->line("**Клиент:** {$clientName}")
            ->line("**Тема:** {$complaint->subject}")
            ->line("**Сообщение:** {$complaint->message}")
            ->action('Открыть жалобу', ComplaintResource::getUrl('view', ['record' => $complaint]))
            ->salutation('Клиномания');
    }
}
