<?php

namespace Modules\Classroom\Console;

use Illuminate\Console\Command;
use Modules\Classroom\Actions\Recordings\SweepRecordings;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every five minutes in each academy: stops recordings of lessons that are over, settles quiet ones, removes expired files. */
final class RecordingsCommand extends Command
{
    protected $signature = 'classroom:recordings';

    protected $description = 'Stop recordings of finished lessons, settle ones the recorder went quiet about, and remove expired recordings';

    public function handle(EachTenant $each, SweepRecordings $sweep): int
    {
        $totals = ['stopped' => 0, 'settled' => 0, 'removed' => 0];
        $each->sum(function () use ($sweep, &$totals) {
            foreach ($sweep->handle() as $key => $count) {
                $totals[$key] += $count;
            }

            return 0;
        });
        $this->info("{$totals['stopped']} stopped, {$totals['settled']} settled, {$totals['removed']} removed.");

        return self::SUCCESS;
    }
}
