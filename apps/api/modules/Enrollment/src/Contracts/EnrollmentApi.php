<?php

namespace Modules\Enrollment\Contracts;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;

/** What other modules may ask of Enrollment. */
interface EnrollmentApi
{
    /** Opens an enrollment awaiting its first payment and returns its id. */
    public function create(NewEnrollment $new): string;

    public function attachCreditAccount(string $enrollmentId, string $creditAccountId): void;

    /** First payment in: pending_payment becomes active. Idempotent. */
    public function activate(string $enrollmentId, ?string $actorId = null): void;

    /** The teacher from $from on; the current assignment ends the day before. */
    public function reassignTeacher(string $enrollmentId, string $teacherId, CarbonImmutable $from, ?string $actorId = null): void;

    public function find(string $enrollmentId): ?EnrollmentData;

    /** @return list<EnrollmentData> enrollments funded by this credit account */
    public function forCreditAccount(string $creditAccountId): array;
}
