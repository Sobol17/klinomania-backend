<?php

namespace App\Filament\Resources\CleaningOrders\Pages;

use App\Filament\Resources\CleaningOrders\CleaningOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCleaningOrders extends ListRecords
{
    protected static string $resource = CleaningOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
