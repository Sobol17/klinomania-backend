<?php

namespace App\Modules\Notifications\Services;

use App\Enums\OrderStatus;
use App\Models\CleaningOrder;
use App\Modules\Notifications\Contracts\PushGateway;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Exception\MessagingException;

class OrderStatusPushService
{
    public function __construct(private readonly Container $container) {}

    public function send(int $orderId, OrderStatus $status): void
    {
        $order = CleaningOrder::query()->with('client.clientProfile')->find($orderId);
        $profile = $order?->client?->clientProfile;

        if ($order === null || $profile === null || ! $profile->push_notifications_enabled || $profile->fcm_token === null) {
            return;
        }

        $token = $profile->fcm_token;
        $pushGateway = $this->container->make(PushGateway::class);

        try {
            $pushGateway->send(
                $token,
                'Клиномания',
                $this->statusMessage($status),
                ['order_id' => $order->public_id],
            );
        } catch (NotFound|InvalidArgument) {
            $profile->newQuery()
                ->whereKey($profile->id)
                ->where('fcm_token', $token)
                ->update(['fcm_token' => null]);
        } catch (ServerUnavailable|ServerError|ApiConnectionFailed|QuotaExceeded $exception) {
            throw $exception;
        } catch (MessagingException $exception) {
            Log::error('Firebase push notification could not be delivered.', [
                'order_id' => $order->public_id,
                'exception' => $exception,
            ]);
        }
    }

    private function statusMessage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Processing => 'Ваша заявка на уборку принята и находится в обработке.',
            OrderStatus::Confirmed => 'Ваша заявка на уборку подтверждена!',
            OrderStatus::TeamFormed => 'Команда клинеров для вашей уборки сформирована!',
            OrderStatus::InProgress => 'Клинеры приступили к вашей уборке.',
            OrderStatus::AwaitingPayment => 'Уборка завершена! Осталось оплатить заказ.',
            OrderStatus::Completed => 'Спасибо! Оплата получена, заявка завершена.',
            OrderStatus::Cancelled => 'Ваша заявка на уборку отменена.',
        };
    }
}
