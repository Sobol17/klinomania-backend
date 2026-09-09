<?php

namespace App\Filament\Resources\PaymentAttempts\Pages;

use App\Filament\Resources\PaymentAttempts\PaymentAttemptResource;
use Filament\Resources\Pages\EditRecord;

class EditPaymentAttempt extends EditRecord
{
    protected static string $resource = PaymentAttemptResource::class;
}
