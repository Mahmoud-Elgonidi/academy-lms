<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Daily: the DST planner looks 45 days ahead in every academy, and tells families of their new hour 14 and 2 days before. */
final class PlanDstCommand extends Command
{
    protected $signature = 'scheduling:dst';

    protected $description = 'Find lessons whose hour moves at a clock change in the next 45 days, and tell families in time';

    public function handle(EachTenant $each, PlanDstChanges $plan, SendDstNotices $send): int
    {
        $found = $each->sum(fn () => $plan->handle());
        $told = $each->sum(fn () => $send->handle());

        $this->info("{$found} new change(s) found, {$told} notice(s) sent.");

        return self::SUCCESS;
    }
}
