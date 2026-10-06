<?php

namespace Modules\Enrollment\Contracts\Events;

/** The first payment arrived; the enrollment is under way. */
final readonly class EnrollmentActivated
{
    public function __construct(public string $academyId, public string $enrollmentId) {}
}
