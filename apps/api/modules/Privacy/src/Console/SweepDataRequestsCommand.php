<?php

namespace Modules\Privacy\Console;

use Illuminate\Console\Command;
use Modules\Privacy\Actions\SweepDataRequests;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every quarter of an hour in each academy: approved erasures done once their day has passed, week-old copies removed. */
final class SweepDataRequestsCommand extends Command
{
    protected $signature = 'privacy:sweep';

    protected $description = 'Erase approved accounts whose day has passed and remove expired copies of personal data, in every academy';

    public function handle(EachTenant $each, SweepDataRequests $sweep): int
    {
        $erased = 0;
        $expired = 0;
        $each->sum(function () use ($sweep, &$erased, &$expired) {
            $done = $sweep->handle();
            $erased += $done['erased'];
            $expired += $done['expired'];

            return $done['erased'] + $done['expired'];
        });
        $this->info("{$erased} account(s) erased, {$expired} copy(ies) removed.");

        return self::SUCCESS;
    }
}
