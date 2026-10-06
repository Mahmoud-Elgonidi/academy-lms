<?php

namespace Modules\Enrollment\Contracts\Events;

/** The enrollment now follows another program (or none); Learning rebuilds the student's progress. */
final readonly class CurriculumAttached
{
    public function __construct(
        public string $academyId,
        public string $enrollmentId,
        public ?string $programId,
        public ?string $versionId,
    ) {}
}
