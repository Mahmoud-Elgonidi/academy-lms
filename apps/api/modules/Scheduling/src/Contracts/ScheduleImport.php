<?php

namespace Modules\Scheduling\Contracts;

use Modules\Scheduling\Contracts\DTOs\BroughtSchedule;
use Modules\Scheduling\Contracts\DTOs\ExistingSchedule;

/**
 * Brings a running schedule over from an academy's spreadsheets
 * (docs/architecture/01: "استيراد من CSV/Excel"): an active enrollment with
 * its weekly times and lessons ahead, the minutes left of the block already
 * paid, and, on a plan, renewals from the next block.
 */
interface ScheduleImport
{
    /** The student already has a live enrollment for this subject with this teacher. */
    public function exists(string $studentId, string $teacherId, string $subjectId): bool;

    /** All or nothing; a ValidationException says what is wrong (a time taken, a plan that does not fit, …). */
    public function bring(ExistingSchedule $schedule): BroughtSchedule;
}
