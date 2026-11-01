<?php

namespace Modules\Enrollment\Contracts\Events;

/**
 * The first payment arrived; the enrollment is under way. $reason: paid (its
 * first payment), package (it joined a paid shared package) or imported (a
 * course brought over, already under way before the platform).
 */
final readonly class EnrollmentActivated
{
    public function __construct(public string $academyId, public string $enrollmentId, public string $reason = 'paid') {}
}
