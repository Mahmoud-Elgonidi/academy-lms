<?php

namespace Modules\Scheduling\Contracts\Events;

/** A group has the students it waited for and runs from today: its lessons are made, and each member hears it. */
final readonly class GroupStarted
{
    /** @param  list<string>  $studentIds */
    public function __construct(
        public string $academyId,
        public string $groupId,
        public string $teacherId,
        public array $studentIds,
        public string $startedOn,
        public ?string $actorId,
    ) {}
}
