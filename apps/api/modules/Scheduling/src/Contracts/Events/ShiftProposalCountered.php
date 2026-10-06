<?php

namespace Modules\Scheduling\Contracts\Events;

/** An answer to a teacher's proposal; Scheduling tells the teacher through Communication. */
final readonly class ShiftProposalCountered
{
    public function __construct(
        public string $academyId,
        public string $proposalId,
        public string $teacherId,
        public ?string $actorId,
    ) {}
}
