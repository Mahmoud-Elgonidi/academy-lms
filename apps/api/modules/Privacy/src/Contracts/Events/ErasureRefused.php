<?php

namespace Modules\Privacy\Contracts\Events;

/** The academy refused an erasure, with its reason. */
final readonly class ErasureRefused
{
    public function __construct(public string $requestId) {}
}
