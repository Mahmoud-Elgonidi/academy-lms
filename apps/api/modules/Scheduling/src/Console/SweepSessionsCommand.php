<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/** Runs every minute: applies attendance deadlines in each academy, as that academy. */
final class SweepSessionsCommand extends Command
{
    protected $signature = 'scheduling:sweep';

    protected $description = 'Apply attendance deadlines (teacher absent, student absent, completed) in every academy';

    public function handle(TenantDirectory $directory, TenantContext $tenants, ApplyAttendanceDeadlines $deadlines): int
    {
        $changed = 0;

        foreach ($directory->active() as $academy) {
            /** @var TenantRef $academy */
            $changed += $tenants->run($academy, fn () => $deadlines->handle());
        }

        $this->info("{$changed} session(s) updated.");

        return self::SUCCESS;
    }
}
