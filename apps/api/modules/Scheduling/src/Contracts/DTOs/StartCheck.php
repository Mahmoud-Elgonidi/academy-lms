<?php

namespace Modules\Scheduling\Contracts\DTOs;

/**
 * Whether the start flow can open: available, or why not (a reason key and
 * its sentence for the family: what to do instead). $teacherId is the teacher
 * asked about when they still take this learner in the subject (else null:
 * the family chooses another).
 */
final readonly class StartCheck
{
    public function __construct(
        public bool $available,
        public ?string $reason,
        public ?string $whyNot,
        public ?string $teacherId,
        public ?string $enrollmentId = null,     // the learner's course in the subject, when that is why
    ) {}
}
