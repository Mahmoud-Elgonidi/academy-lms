<?php

namespace Modules\Privacy\Contracts\Events;

/** An account was anonymised; what the law keeps stayed. */
final readonly class AccountErased
{
    public function __construct(public string $requestId) {}
}
