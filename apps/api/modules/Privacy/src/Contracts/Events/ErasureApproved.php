<?php

namespace Modules\Privacy\Contracts\Events;

/** The academy approved an erasure: the account goes a day later. */
final readonly class ErasureApproved
{
    public function __construct(public string $requestId) {}
}
