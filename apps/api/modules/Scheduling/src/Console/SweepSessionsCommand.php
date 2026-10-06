<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/** Runs every minute in each academy, as that academy: attendance deadlines, and unpaid sessions cancelled (rule 16). */
final class SweepSessionsCommand extends Command
{
    protected $signature = 'scheduling:sweep';

    protected $description = 'Apply attendance deadlines and cancel unpaid enrolled sessions in every academy';

    public function handle(TenantDirectory $directory, TenantContext $tenants, ApplyAttendanceDeadlines $deadlines, CancelUnpaidSessions $unpaid): int
    {
        $changed = 0;

        foreach ($directory->active() as $academy) {
            /** @var TenantRef $academy */
            $changed += $tenants->run($academy, fn () => $deadlines->handle() + $unpaid->handle());
        }

        $this->info("{$changed} session(s) updated.");

        return self::SUCCESS;
    }
}
