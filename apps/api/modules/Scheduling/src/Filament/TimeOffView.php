<?php

namespace Modules\Scheduling\Filament;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Queries\TimeOffCover;

/** Time off and holidays in words: the days or hours on the clock they were set on, and where the lessons stand. */
final class TimeOffView
{
    public static function period(TeacherTimeOff $t): string
    {
        $l = 'scheduling::timeoff.period.';
        $start = $t->starts_at->setTimezone($t->timezone);
        if (! $t->all_day) {
            return __($l.'hours', ['day' => self::day($start), 'from' => $start->format('H:i'), 'to' => $t->ends_at->setTimezone($t->timezone)->format('H:i')]);
        }
        $last = $t->lastDay();

        return $start->isSameDay($last) ? __($l.'day', ['day' => self::day($start)]) : __($l.'days', ['from' => self::day($start), 'to' => self::day($last)]);
    }

    public static function holiday(AcademyHoliday $h): string
    {
        $l = 'scheduling::timeoff.period.';

        return $h->first_day->isSameDay($h->last_day)
            ? __($l.'day', ['day' => self::day($h->first_day)])
            : __($l.'days', ['from' => self::day($h->first_day), 'to' => self::day($h->last_day)]);
    }

    public static function day(CarbonImmutable $day): string
    {
        return $day->locale(app()->getLocale())->translatedFormat('l j F');
    }

    /** Where a lesson of the leave stands: still the teacher's, with a substitute, cancelled, or over. */
    public static function state(Session $s, TeacherTimeOff $t): string
    {
        return match (true) {
            $s->status === SessionStatus::Cancelled => 'cancelled',
            ! in_array($s->status, [SessionStatus::Scheduled, SessionStatus::InProgress], true) => 'done',
            $s->teacher_id === $t->teacher_id => 'needs_cover',
            default => 'covered',
        };
    }

    /** @return array{total: int, covered: int, cancelled: int, left: int} */
    public static function summary(TeacherTimeOff $t): array
    {
        $states = array_map(fn (Session $s) => self::state($s, $t), app(TimeOffCover::class)->lessons($t));
        $count = array_count_values($states);

        return ['total' => count($states), 'covered' => $count['covered'] ?? 0, 'cancelled' => $count['cancelled'] ?? 0, 'left' => $count['needs_cover'] ?? 0];
    }
}
