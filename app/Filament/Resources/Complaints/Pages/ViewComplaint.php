<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComplaintResource::statusAction('take', 'Взять в работу', ComplaintStatus::InProgress, 'warning'),
            ComplaintResource::statusAction('resolve', 'Решена', ComplaintStatus::Resolved, 'success', true),
            ComplaintResource::statusAction('reject', 'Отклонена', ComplaintStatus::Rejected, 'danger', true),
            EditAction::make(),
        ];
    }
}
