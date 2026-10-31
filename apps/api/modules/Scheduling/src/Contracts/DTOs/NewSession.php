<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** One lesson to book: a teacher and a student, or a group's lesson (no student here: its members are seated one by one). */
final readonly class NewSession
{
    public function __construct(
        public string $teacherId,
        public ?string $studentId,
        public string $subjectId,
        public CarbonImmutable $startsAt,
        public int $durationMin,
        public string $anchorTimezone,
        public string $type = 'regular',
        public string $funding = 'funded',
        public ?string $enrollmentId = null,
        public ?string $slotId = null,
        public ?string $occurrenceKey = null,
        public ?string $groupId = null,
    ) {}
}
