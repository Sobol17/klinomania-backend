<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\UserRole;

class ClientOrdersRelationManager extends OrdersRelationManager
{
    protected static string $relationship = 'clientOrders';

    protected static UserRole $ownerRole = UserRole::Client;
}
