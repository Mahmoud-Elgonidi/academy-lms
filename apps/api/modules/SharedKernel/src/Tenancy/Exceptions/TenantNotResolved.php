<?php

namespace Modules\SharedKernel\Tenancy\Exceptions;

use RuntimeException;

/** No academy could be determined for this request, or code needed one and none was set. */
final class TenantNotResolved extends RuntimeException
{
    /** @param  bool  $byRequest  the request named no academy (the caller's mistake, answered 404): not one of the app's errors */
    public function __construct(string $message = 'No academy was resolved for this request.', public readonly bool $byRequest = false)
    {
        parent::__construct($message);
    }

    public static function forRequest(): self
    {
        return new self(byRequest: true);
    }
}
