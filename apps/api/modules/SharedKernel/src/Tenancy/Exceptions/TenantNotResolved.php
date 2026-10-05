<?php

namespace Modules\SharedKernel\Tenancy\Exceptions;

use RuntimeException;

/** No academy could be determined for this request, or code needed one and none was set. */
final class TenantNotResolved extends RuntimeException
{
    public function __construct(string $message = 'No academy was resolved for this request.')
    {
        parent::__construct($message);
    }
}
