<?php

namespace Modules\Site\Commands;

use Illuminate\Console\Command;
use Modules\SharedKernel\Tenancy\EachTenant;
use Modules\Site\Models\JoinRequest;

/**
 * Requests from an academy's page leave a year after they came, whatever
 * became of them: the academy keeps a visitor's name and number only as long
 * as it may still answer them (a trial's lead stays in the CRM, on its own terms).
 */
final class SweepJoinRequests extends Command
{
    public const KEEP_MONTHS = 12;

    protected $signature = 'site:sweep';

    protected $description = "Remove requests from the academies' pages older than a year";

    public function handle(EachTenant $each): int
    {
        $gone = $each->sum(fn () => (int) JoinRequest::query()->where('created_at', '<', now()->subMonths(self::KEEP_MONTHS))->delete());
        $this->line("removed {$gone} old request(s)");

        return self::SUCCESS;
    }
}
