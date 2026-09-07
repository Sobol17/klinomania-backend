<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Все')];
        foreach (UserRole::cases() as $role) {
            $tabs[$role->value] = Tab::make($role->getLabel())
                ->modifyQueryUsing(fn ($query) => $query->where('role', $role));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
