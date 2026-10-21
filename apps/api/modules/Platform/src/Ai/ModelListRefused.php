<?php

namespace Modules\Platform\Ai;

use Modules\SharedKernel\Health\ServiceFault;
use RuntimeException;

/** The provider would not list its models, and why. */
final class ModelListRefused extends RuntimeException
{
    public function __construct(public readonly ServiceFault $fault)
    {
        parent::__construct($fault->problem->value);
    }
}
