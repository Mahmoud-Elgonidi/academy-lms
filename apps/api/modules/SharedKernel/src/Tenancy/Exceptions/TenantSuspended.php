<?php

namespace Modules\SharedKernel\Tenancy\Exceptions;

use RuntimeException;

final class TenantSuspended extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This academy is suspended.');
    }
}
