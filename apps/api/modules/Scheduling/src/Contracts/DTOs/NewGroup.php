<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A group to open with a teacher who takes groups: the subject (and level),
 * the ages it is for (null: its first member's), its seats (null: the
 * teacher's most), the students it waits for before it starts (null: the
 * academy's default, else its first), the lesson length, and its weekly
 * times on the teacher's clock from a day on.
 */
final readonly class NewGroup
{
    /** @param  list<array{weekday: int, start: string}>  $times  ISO weekday and "H:i" on the teacher's clock */
    public function __construct(
        public string $teacherId,
        public string $subjectId,
        public int $sessionMinutes,
        public array $times,
        public ?string $levelId = null,
        public ?string $ageGroup = null,
        public ?int $capacity = null,
        public ?int $minToStart = null,
        public ?CarbonImmutable $startsOn = null,
        public ?string $name = null,
    ) {}
}
