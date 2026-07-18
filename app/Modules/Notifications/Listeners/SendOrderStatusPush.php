<?php

namespace App\Modules\Notifications\Listeners;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Modules\Notifications\Contracts\PushGateway;
use App\Modules\Notifications\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Exception\MessagingException;

class SendOrderStatusPush implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function handle(OrderStatusChanged $event): void
    {
        $order = CleaningOrder::query()->with('client.clientProfile')->find($event->orderId);
        $profile = $order?->client?->clientProfile;

        if ($order === null || $profile === null || ! $profile->push_notifications_enabled || $profile->fcm_token === null) {
            return;
        }

        $token = $profile->fcm_token;
        $pushGateway = app(PushGateway::class);

        try {
            $pushGateway->send(
                $token,
                'Статус заявки изменён',
                "Заявка {$order->public_id}: {$this->statusLabel($event->status)}",
                ['order_id' => $order->public_id],
            );
        } catch (NotFound|InvalidArgument) {
            $profile->newQuery()->whereKey($profile->id)->where('fcm_token', $token)->update(['fcm_token' => null]);
        } catch (ServerUnavailable|ServerError|ApiConnectionFailed|QuotaExceeded $exception) {
            throw $exception;
        } catch (MessagingException $exception) {
            Log::error('Firebase push notification could not be delivered.', [
                'order_id' => $order->public_id,
                'exception' => $exception,
            ]);
        }
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Processing => 'В обработке',
            OrderStatus::Confirmed => 'Подтверждена',
            OrderStatus::TeamFormed => 'Команда сформирована',
            OrderStatus::InProgress => 'В работе',
            OrderStatus::AwaitingPayment => 'Ожидает оплаты',
            OrderStatus::Completed => 'Выполнена',
            OrderStatus::Cancelled => 'Отменена',
        };
    }
}
