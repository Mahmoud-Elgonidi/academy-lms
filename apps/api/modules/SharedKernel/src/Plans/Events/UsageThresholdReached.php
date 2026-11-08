<?php

namespace Modules\SharedKernel\Plans\Events;

/**
 * An academy's use of a meter reached 50%, 80% or 100% of this month's
 * allowance (once per academy, meter, month and threshold). Raised by
 * Platform inside the academy's context; Communication tells the staff who
 * see the plan.
 */
final readonly class UsageThresholdReached
{
    public function __construct(
        public string $academyId,
        public string $meter,        // SharedKernel\Plans\Meter value
        public int $threshold,       // 50, 80 or 100
        public string $period,       // the month's first day, on the academy's clock
        public int $used,
        public int $allowance,
        public int $bought,          // what is left of the bought balance
    ) {}
}
