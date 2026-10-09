<?php

namespace Modules\People\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * Whether a teacher may take new lessons with students under 18
 * (docs/architecture/02, section 15). Paused (a safeguarding concern is
 * being looked into) always says no; otherwise, when the academy asks for a
 * check, only a verified criminal record check that has not run out says yes.
 */
final readonly class TeacherClearance
{
    public const NOT_REQUIRED = 'not_required';

    public const VERIFIED = 'verified';

    public const PENDING = 'pending';

    public const EXPIRED = 'expired';

    public const MISSING = 'missing';

    public const PAUSED = 'paused';

    public function __construct(
        public string $teacherId,
        public bool $cleared,
        public string $state,               // one of the constants
        public bool $required,              // the academy asks for a check before minors
        public ?CarbonImmutable $expiresOn = null,  // the verified check's last day
    ) {}

    /** The check runs out within this many days (a reminder is due). */
    public function expiresWithin(int $days, CarbonImmutable $today): bool
    {
        return $this->expiresOn !== null && $this->expiresOn->toDateString() <= $today->addDays($days)->toDateString();
    }
}
