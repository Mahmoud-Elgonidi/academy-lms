<?php

namespace Modules\Scheduling\Contracts\Events;

/** A teacher's approved schedule changed from a date on; availability caches for those weeks are stale. */
final readonly class ShiftRulesChanged
{
    public function __construct(
        public string $academyId,
        public string $teacherId,
        public string $effectiveFrom,
        public int $version,
    ) {}
}
