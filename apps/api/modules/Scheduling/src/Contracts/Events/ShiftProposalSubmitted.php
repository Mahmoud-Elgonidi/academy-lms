<?php

namespace Modules\Scheduling\Contracts\Events;

/** A teacher sent a proposal; Scheduling tells the reviewers through Communication. */
final readonly class ShiftProposalSubmitted
{
    public function __construct(
        public string $academyId,
        public string $proposalId,
        public string $teacherId,
        public ?string $actorId,
    ) {}
}
