<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\SendLessonReminders;
use Modules\Scheduling\Actions\TimeOff\CancelUncoveredLessons;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Runs every minute in each academy, as that academy: attendance deadlines, unpaid sessions cancelled (rule 16), lessons of a teacher on leave nobody covered, reminders and "join now". */
final class SweepSessionsCommand extends Command
{
    protected $signature = 'scheduling:sweep';

    protected $description = 'Apply attendance deadlines, cancel unpaid enrolled sessions, and send lesson reminders in every academy';

    public function handle(EachTenant $each, ApplyAttendanceDeadlines $deadlines, CancelUnpaidSessions $unpaid, CancelUncoveredLessons $uncovered, SendLessonReminders $reminders): int
    {
        $changed = $each->sum(function () use ($deadlines, $unpaid, $uncovered, $reminders) {
            $changed = $deadlines->handle() + $unpaid->handle() + $uncovered->handle();
            $reminders->handle();

            return $changed;
        });

        $this->info("{$changed} session(s) updated.");

        return self::SUCCESS;
    }
}
