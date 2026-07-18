<?php

namespace App\Modules\Payments\Contracts;

use App\Models\PaymentAttempt;

interface TBankGateway
{
    /** @return array{payment_id: string, payment_url: string, provider_status: string} */
    public function initialize(PaymentAttempt $attempt, string $description): array;
}
