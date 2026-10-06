<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\SendLessonReminders;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/** Runs every minute in each academy, as that academy: attendance deadlines, unpaid sessions cancelled (rule 16), reminders and "join now". */
final class SweepSessionsCommand extends Command
{
    protected $signature = 'scheduling:sweep';

    protected $description = 'Apply attendance deadlines, cancel unpaid enrolled sessions, and send lesson reminders in every academy';

    public function handle(TenantDirectory $directory, TenantContext $tenants, ApplyAttendanceDeadlines $deadlines, CancelUnpaidSessions $unpaid, SendLessonReminders $reminders): int
    {
        $changed = 0;

        foreach ($directory->active() as $academy) {
            /** @var TenantRef $academy */
            $changed += $tenants->run($academy, function () use ($deadlines, $unpaid, $reminders) {
                $changed = $deadlines->handle() + $unpaid->handle();
                $reminders->handle();

                return $changed;
            });
        }

        $this->info("{$changed} session(s) updated.");

        return self::SUCCESS;
    }
}
