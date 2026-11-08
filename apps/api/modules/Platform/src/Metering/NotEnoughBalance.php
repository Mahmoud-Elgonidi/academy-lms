<?php

namespace Modules\Platform\Metering;

use RuntimeException;

/** A hold that does not fit what is left: the request does not start. */
final class NotEnoughBalance extends RuntimeException
{
    public function __construct(public readonly int $needed, public readonly int $available)
    {
        parent::__construct("Needs {$needed}, {$available} left.");
    }
}
