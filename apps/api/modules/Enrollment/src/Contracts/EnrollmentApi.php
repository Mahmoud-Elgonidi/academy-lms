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

    /** The program the student follows, pinned to its published version. Null clears it. */
    public function attachCurriculum(string $enrollmentId, ?string $programId, ?string $actorId = null): void;

    /**
     * Pauses a running course from $from to $until (days on the academy's calendar, both included, from $today on):
     * paused now when $from has come, else from that day (settleDays). A pause already set may only be made longer.
     * Returns the course as it was. Raises EnrollmentPaused.
     */
    public function pause(string $enrollmentId, CarbonImmutable $from, CarbonImmutable $until, string $reason, CarbonImmutable $today, ?string $actorId = null): EnrollmentData;

    /** The pause is over, or called off before it began: the course is on again. Returns it as it was. Raises EnrollmentResumed. */
    public function resume(string $enrollmentId, bool $early, ?string $actorId = null): EnrollmentData;

    /**
     * The course ends from $on (its first day without lessons, today or later on the academy's calendar): ended now
     * when that day has come, else on it (settleDays). Returns it as it was. Raises EnrollmentEnded.
     */
    public function end(string $enrollmentId, CarbonImmutable $on, string $reason, CarbonImmutable $today, ?string $actorId = null): EnrollmentData;

    /** The status its days give it on $today: ended once its end has come, paused once its pause has begun. The new status, when it moved. */
    public function settleDays(string $enrollmentId, CarbonImmutable $today): ?EnrollmentStatus;

    /** @return list<EnrollmentData> the courses with a pause or an end set, to follow day by day */
    public function withDays(): array;

    /**
     * The office's words for the course's pause and end (never the family's to read).
     *
     * @return array{pause: ?string, end: ?string}
     */
    public function stopReasons(string $enrollmentId): array;

    public function find(string $enrollmentId): ?EnrollmentData;

    /** @return list<EnrollmentData> the student's enrollments, newest first */
    public function forStudent(string $studentId): array;

    /** @return list<EnrollmentData> enrollments funded by this credit account */
    public function forCreditAccount(string $creditAccountId): array;
}
