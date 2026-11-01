<?php

namespace Modules\Quran\Domain\Programs;

/**
 * Whether a learner keeps their plan's pace (docs/architecture/02, section
 * 26), purely, from their last lessons recorded on it: on track when at
 * least two thirds of them moved past the new part, behind when fewer did;
 * new before any lesson is recorded; and as it stands when the plan is done
 * or frozen (the course paused or over), where pace means nothing.
 */
final class Pace
{
    /** The lessons recorded that count, newest first. */
    public const LESSONS = 6;

    /** The days a lesson counts for. */
    public const DAYS = 30;

    public const STATUSES = ['on_track', 'behind', 'new', 'frozen', 'finished'];

    /**
     * @param  int  $recorded  lessons recorded in the last DAYS days (at most LESSONS)
     * @param  int  $advanced  of them, those whose new part passed
     */
    public static function status(int $recorded, int $advanced, bool $frozen = false, bool $finished = false): string
    {
        return match (true) {
            $finished => 'finished',
            $frozen => 'frozen',
            $recorded <= 0 => 'new',
            $advanced * 3 >= $recorded * 2 => 'on_track',
            default => 'behind',
        };
    }
}
