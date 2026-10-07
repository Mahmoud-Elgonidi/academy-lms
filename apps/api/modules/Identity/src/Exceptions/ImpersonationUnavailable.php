<?php

namespace Modules\Identity\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A one-time sign-in link that was used, ran out, or was never made here. */
final class ImpersonationUnavailable extends HttpException
{
    public function __construct()
    {
        parent::__construct(410, 'This sign-in link no longer works.');
    }
}
