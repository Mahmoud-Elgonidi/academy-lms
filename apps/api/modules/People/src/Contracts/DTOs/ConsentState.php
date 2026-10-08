<?php

namespace Modules\People\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * Where a student stands with a guardian's consent: whether their account
 * needs it (under the academy's consent age, or of unknown age with a
 * guardian) and whether one stands, from whom, how and since when.
 */
final readonly class ConsentState
{
    public function __construct(
        public string $studentId,
        public bool $needed,
        public bool $granted,
        public ?string $consentId = null,
        public ?string $guardianId = null,
        public ?string $guardianName = null,
        public ?string $via = null,
        public ?CarbonImmutable $grantedAt = null,
    ) {}

    /** The child's own sign-in, links and hand-ins wait for a guardian. */
    public function waiting(): bool
    {
        return $this->needed && ! $this->granted;
    }
}
