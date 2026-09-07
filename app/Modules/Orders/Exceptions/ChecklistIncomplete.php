<?php

namespace App\Modules\Orders\Exceptions;

use DomainException;

class ChecklistIncomplete extends DomainException
{
    public function __construct()
    {
        parent::__construct('All checklist items must be completed before finishing the order.');
    }
}
