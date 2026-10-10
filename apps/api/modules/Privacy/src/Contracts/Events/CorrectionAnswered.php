<?php

namespace Modules\Privacy\Contracts\Events;

/** The academy answered a correction: done, with a note, or refused, with its reason. */
final readonly class CorrectionAnswered
{
    public function __construct(public string $requestId) {}
}
