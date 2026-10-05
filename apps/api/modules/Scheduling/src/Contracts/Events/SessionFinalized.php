<?php

namespace Modules\Scheduling\Contracts\Events;

/** The one event a closed session emits; Credits, Payroll, Evaluation and Learning listen. */
final readonly class SessionFinalized
{
    /** @param  array<string, mixed>  $decision */
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $outcome,
        public array $decision,
    ) {}
}
