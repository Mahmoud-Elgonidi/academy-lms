<?php

namespace Modules\Learning\Contracts\Events;

/** A teacher handed in a lesson report or an absence note; Evaluation counts lateness; Learning tells the family through Communication. */
final readonly class SessionReportSubmitted
{
    public function __construct(
        public string $academyId,
        public string $reportId,
        public string $sessionId,
        public ?string $enrollmentId,
        public string $studentId,
        public string $teacherId,
        public string $kind,
        public bool $late,
    ) {}
}
