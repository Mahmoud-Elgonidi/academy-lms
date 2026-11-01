<?php

namespace Modules\Scheduling\Contracts\DTOs;

/**
 * A family starting a course on its own (docs/architecture/02, section 27,
 * J3): who (the viewer, who pays), for which learner and subject, the plan
 * at the price they saw, and either a teacher's weekly times (their keys from
 * the times offered) or a group's seat.
 */
final readonly class OwnStart
{
    /** @param  list<string>  $times  weekly times as offered (GET family/start/slots: key) */
    public function __construct(
        public string $viewerId,
        public string $learnerId,
        public string $subjectId,
        public string $planId,
        public int $priceMinor,
        public string $currency,
        public string $timezone,
        public ?string $teacherId = null,
        public array $times = [],
        public ?string $groupId = null,
    ) {}
}
