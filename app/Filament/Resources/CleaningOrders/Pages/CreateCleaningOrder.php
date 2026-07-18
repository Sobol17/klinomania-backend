<?php

namespace App\Filament\Resources\CleaningOrders\Pages;

use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use App\Models\CleaningService;
use App\Models\User;
use App\Modules\Orders\Actions\CreateOrder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateCleaningOrder extends CreateRecord
{
    protected static string $resource = CleaningOrderResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $client = User::query()->findOrFail($data['client_id']);
        $service = CleaningService::query()->findOrFail($data['cleaning_service_id']);
        $input = [
            'service_id' => $service->slug,
            'room_option_id' => $data['room_option_id'] ?? null,
            'cleaning_option_id' => $data['cleaning_option_id'] ?? null,
            'extra_option_ids' => $data['extra_option_ids'] ?? [],
            'scheduled_at' => $data['scheduled_at'],
            'address' => [
                'full_address' => $data['address'],
                'entrance' => $data['entrance'] ?? null,
                'floor' => $data['floor'] ?? null,
                'apartment' => $data['apartment'] ?? null,
                'intercom' => $data['intercom'] ?? null,
                'comment' => $data['comment'] ?? null,
            ],
        ];

        return app(CreateOrder::class)->execute(
            $client,
            (string) Str::uuid(),
            hash('sha256', json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            $input,
        );
    }
}
