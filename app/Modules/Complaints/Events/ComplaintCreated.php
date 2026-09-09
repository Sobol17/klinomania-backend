<?php

namespace App\Modules\Complaints\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ComplaintCreated implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $complaintId) {}
}
