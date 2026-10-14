<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Scheduling\Actions\Courses\FollowCourseDays;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every hour: courses whose pause begins or is over, or whose end has come, in every academy. */
final class FollowCourseDaysCommand extends Command
{
    protected $signature = 'scheduling:courses';

    protected $description = 'Pause, resume and end courses whose days have come in every academy';

    public function handle(EachTenant $each, FollowCourseDays $follow): int
    {
        $moved = $each->sum(fn () => $follow->handle());
        $this->info("{$moved} course(s) moved.");

        return self::SUCCESS;
    }
}
