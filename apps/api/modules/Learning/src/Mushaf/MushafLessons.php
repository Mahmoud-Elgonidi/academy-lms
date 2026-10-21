<?php

namespace Modules\Learning\Mushaf;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;

/**
 * Which lessons have a shared mushaf, and who may do what in it
 * (docs/architecture/02, section 22): a lesson of a course the hifz map
 * belongs to (HifzMap::follows: its curriculum reads the Quran, or the
 * student has pages), not cancelled. Its teacher turns the pages and marks
 * words, from the room's opening until the lesson's report is handed in (a
 * day after its end at most); its student and the student's guardians
 * follow and read.
 */
final class MushafLessons
{
    /** The room opens this long before the lesson. */
    public const OPENS_BEFORE_MIN = 10;

    /** Words may still be marked this long after the end, until the report is handed in. */
    public const MARKS_AFTER_HOURS = 24;

    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly EnrollmentApi $enrollments,
        private readonly PeopleApi $people,
        private readonly HifzMap $map,
    ) {}

    /** The lesson's mushaf for this person; null when the lesson has none, or it is not theirs. */
    public function for(string $sessionId, string $viewerId): ?MushafLesson
    {
        $card = Str::isUuid($sessionId) ? $this->attendance->lesson($sessionId) : null;
        if ($card === null || $card->studentId === null || $card->enrollmentId === null || $card->status === 'cancelled') {
            return null;
        }
        $role = match (true) {
            $viewerId === $card->teacherId => 'teacher',
            $viewerId === $card->studentId => 'student',
            in_array($viewerId, $this->people->guardiansOf($card->studentId), true) => 'guardian',
            default => null,
        };
        $course = $role === null ? null : $this->enrollments->find($card->enrollmentId);
        if ($course === null || ! $this->map->follows($course)) {
            return null;
        }

        return new MushafLesson($card, $viewerId, $role);
    }

    /** Whether the teacher may mark words in it now. */
    public function marksOpen(MushafLesson $lesson, CarbonImmutable $now): bool
    {
        return $lesson->teaches()
            && $now->greaterThanOrEqualTo($lesson->card->startsAt->subMinutes(self::OPENS_BEFORE_MIN))
            && $now->lessThanOrEqualTo($lesson->card->endsAt->addHours(self::MARKS_AFTER_HOURS))
            && ! SessionReport::query()->where('session_id', $lesson->card->id)->whereIn('status', ['submitted', 'reviewed'])->exists();
    }
}
