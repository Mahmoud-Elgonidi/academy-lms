<?php

namespace Modules\Scheduling\Contracts;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/**
 * What the classroom tells Scheduling and asks of it. Scheduling owns the
 * lesson, its participants and attendance; Classroom owns rooms and the
 * video provider, and reports who came and went.
 */
interface AttendanceApi
{
    public function lesson(string $sessionId): ?LessonCard;

    /** @return list<LessonCard> the teacher's lessons starting in [from, to), by time */
    public function lessonsOf(string $teacherId, CarbonImmutable $from, CarbonImmutable $to): array;

    /** @return list<string> ids of open lessons whose classroom is open at $at (from T−$beforeMin to the end + $afterMin) */
    public function lessonsInClass(CarbonImmutable $at, int $beforeMin, int $afterMin): array;

    /** @return list<string> users with an open stretch in the lesson's classroom */
    public function inClassroom(string $sessionId): array;

    public function participantJoined(string $sessionId, string $userId, CarbonImmutable $at, string $source = 'provider'): void;

    public function participantLeft(string $sessionId, string $userId, CarbonImmutable $at, string $source = 'provider'): void;

    public function connectionDropped(string $sessionId, string $userId, int $seconds, CarbonImmutable $at): void;
}
