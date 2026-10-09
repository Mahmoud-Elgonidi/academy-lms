<?php

namespace Modules\Safeguarding\Console;

use Illuminate\Console\Command;
use Modules\Safeguarding\Actions\FollowUpConcerns;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Concerns nobody took within the hour: the leads hear again. */
final class FollowUpConcernsCommand extends Command
{
    protected $signature = 'safeguarding:follow-up';

    protected $description = 'Remind the safeguarding leads of concerns nobody took within the hour';

    public function handle(EachTenant $each, FollowUpConcerns $follow): int
    {
        $raised = $each->sum(fn () => $follow->handle());
        $this->info("{$raised} concern(s) raised again.");

        return self::SUCCESS;
    }
}
