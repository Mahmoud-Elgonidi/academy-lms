<?php

namespace Modules\Learning\Console;

use Illuminate\Console\Command;
use Modules\Learning\Actions\MarkMissedHomework;
use Modules\Learning\Actions\MarkOverdueReports;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every five minutes in each academy: reports past their deadline, homework past its due time. */
final class SweepLearningCommand extends Command
{
    protected $signature = 'learning:sweep';

    protected $description = 'Mark overdue lesson reports and missed homework in every academy';

    public function handle(EachTenant $each, MarkOverdueReports $reports, MarkMissedHomework $homework): int
    {
        $changed = $each->sum(fn () => $reports->handle() + $homework->handle());
        $this->info("{$changed} report(s) or homework updated.");

        return self::SUCCESS;
    }
}
