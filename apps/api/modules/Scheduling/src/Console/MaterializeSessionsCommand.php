<?php

namespace Modules\Scheduling\Console;

use Illuminate\Console\Command;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Nightly: weekly times become sessions up to the horizon, and new sessions are funded where credit allows. */
final class MaterializeSessionsCommand extends Command
{
    protected $signature = 'scheduling:materialize';

    protected $description = 'Generate sessions from weekly times up to the horizon in every academy';

    public function handle(EachTenant $each, MaterializeSessions $materialize, FundSessions $fund): int
    {
        $created = $each->sum(function () use ($materialize, $fund) {
            $result = $materialize->handle();
            foreach (array_unique(array_map(fn ($s) => $s->enrollment_id, $result['created'])) as $enrollmentId) {
                $fund->handle($enrollmentId);
            }

            return count($result['created']);
        });

        $this->info("{$created} session(s) generated.");

        return self::SUCCESS;
    }
}
