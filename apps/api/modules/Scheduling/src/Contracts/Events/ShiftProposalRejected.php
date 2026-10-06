<?php

namespace Modules\Scheduling\Contracts\Events;

/** Communication tells the other side (WhatsApp and push) when it lands. */
final readonly class ShiftProposalRejected
{
    public function __construct(
        public string $academyId,
        public string $proposalId,
        public string $teacherId,
        public ?string $actorId,
    ) {}
}
