<?php

namespace Modules\SharedKernel\Plans;

use Carbon\CarbonImmutable;

/**
 * A lesson's room on our own video, as the classroom saw it open and close:
 * what VideoMeter counts (its actual open time, up to the lesson's length and
 * a margin), for the lesson's teacher.
 */
final readonly class RoomMinutes
{
    /** A room counts at most its lesson's length and this (the platform's VideoCharge holds the same). */
    public const MARGIN_MINUTES = 15;

    public function __construct(
        public string $roomId,
        public string $sessionId,
        public ?string $teacherId,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public int $lessonMinutes,
    ) {}
}
