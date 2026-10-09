<?php

namespace Modules\Privacy\Contracts\Events;

/** Someone asked for an account to be erased: the academy has 30 days to answer. */
final readonly class ErasureRequested
{
    public function __construct(public string $requestId) {}
}
