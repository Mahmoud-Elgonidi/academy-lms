<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Actions\FollowUpLeads;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every hour in each academy: leads nobody answered within its hours, and trials still waiting for their assessment. */
final class FollowUpLeadsCommand extends Command
{
    protected $signature = 'crm:follow-up';

    protected $description = 'Remind whoever follows a lead about new leads nobody answered in time and trials with no assessment, in every academy';

    public function handle(EachTenant $each, FollowUpLeads $followUp): int
    {
        $sent = $each->sum(fn () => $followUp->handle());
        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
