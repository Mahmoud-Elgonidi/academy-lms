<?php

namespace Modules\Enrollment\Contracts\Events;

/**
 * A paused course is on again (or its pause was called off before it
 * began): lessons are made from today. $early: before the day after the
 * pause's last day; $actorId null: on time, by itself.
 */
final readonly class EnrollmentResumed
{
    public function __construct(
        public string $academyId,
        public string $enrollmentId,
        public string $studentId,
        public bool $early,
        public ?string $actorId = null,
    ) {}
}
