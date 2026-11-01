<?php

namespace Modules\Quran\Domain\Exams;

use Carbon\CarbonImmutable;
use Modules\Quran\Domain\Grading\Grades;

/**
 * An exam's outcome (docs/architecture/02, section 26): passed when its
 * grade meets the program's pass grade, else failed; a fail must say why
 * (the examiner's note) and when to try again, a day after today and at
 * most RETAKE_MAX_DAYS ahead on the academy's calendar.
 */
final class ExamOutcome
{
    public const RETAKE_MAX_DAYS = 180;

    public static function passed(string $grade, string $passGrade): bool
    {
        return Grades::meets($grade, $passGrade);
    }

    /**
     * What a fail is missing: note, retake_on; none for a pass.
     *
     * @return list<string>
     */
    public static function missing(bool $passed, ?string $note, ?CarbonImmutable $retakeOn, CarbonImmutable $today): array
    {
        if ($passed) {
            return [];
        }
        $missing = [];
        if (trim((string) $note) === '') {
            $missing[] = 'note';
        }
        // Calendar days compared as days (today is on the academy's clock, the retake a day as typed).
        $day = $retakeOn?->toDateString();
        if ($day === null || $day <= $today->toDateString() || $day > $today->addDays(self::RETAKE_MAX_DAYS)->toDateString()) {
            $missing[] = 'retake_on';
        }

        return $missing;
    }
}
