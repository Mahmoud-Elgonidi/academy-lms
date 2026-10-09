<?php

namespace Modules\People\Console;

use Illuminate\Console\Command;
use Modules\People\Actions\Checks\RemindTeacherChecks;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Teachers' documents that run out within a month, or just ran out: the teacher and the academy hear once. */
final class RemindTeacherChecksCommand extends Command
{
    protected $signature = 'people:checks';

    protected $description = 'Remind teachers and the academy of vetting documents that run out soon or ran out';

    public function handle(EachTenant $each, RemindTeacherChecks $remind): int
    {
        $sent = $each->sum(fn () => $remind->handle());
        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
