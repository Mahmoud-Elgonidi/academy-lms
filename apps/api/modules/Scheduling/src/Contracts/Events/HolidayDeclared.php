<?php

namespace Modules\Scheduling\Contracts\Events;

/** The academy will be closed on some days; the lessons then were cancelled, and each person in them hears once. */
final readonly class HolidayDeclared
{
    /** @param  list<string>  $sessionIds  the lessons it cancelled */
    public function __construct(
        public string $academyId,
        public string $holidayId,
        public array $sessionIds,
        public ?string $actorId,
    ) {}
}
