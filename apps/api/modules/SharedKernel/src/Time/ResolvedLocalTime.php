<?php

namespace Modules\SharedKernel\Time;

use Carbon\CarbonImmutable;

final readonly class ResolvedLocalTime
{
    public function __construct(
        public CarbonImmutable $utc,
        public LocalTimeKind $kind,
        /** Minutes the time was moved forward (ShiftedForward only). */
        public int $shiftMinutes = 0,
    ) {}

    public function isExact(): bool
    {
        return $this->kind === LocalTimeKind::Exact;
    }
}
