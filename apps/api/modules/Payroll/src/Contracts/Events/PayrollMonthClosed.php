<?php

namespace Modules\Payroll\Contracts\Events;

/** A payroll month was closed: its statements are final; each teacher hears their total. */
final readonly class PayrollMonthClosed
{
    public function __construct(
        public string $academyId,
        public string $month,           // Y-m-d, the first day
        public string $closedBy,
    ) {}
}
