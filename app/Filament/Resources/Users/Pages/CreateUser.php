<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        if ($record->role === UserRole::Cleaner) {
            $record->cleanerProfile()->firstOrCreate([]);
        }

        if ($record->role === UserRole::Client) {
            $record->clientProfile()->firstOrCreate([]);
        }

        return $record;
    }
}
