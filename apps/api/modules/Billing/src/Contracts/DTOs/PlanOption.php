<?php

namespace Modules\Billing\Contracts\DTOs;

use Modules\SharedKernel\Money\Money;

/** A plan as the enrolment form offers it, with its price in each region. */
final readonly class PlanOption
{
    /**
     * @param  array<string, Money>  $prices  price region id => block price
     * @param  list<string>  $names  the plan's name in each language (to find it by a name typed in either)
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $sessionMinutes,
        public int $sessionsPerBlock,
        public int $sessionsPerWeek,
        public int $validityDays,
        public array $prices,
        public array $names = [],
    ) {}
}
