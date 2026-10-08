<?php

namespace Modules\Support\Console;

use Illuminate\Console\Command;
use Modules\SharedKernel\Tenancy\EachTenant;
use Modules\Support\Actions\FollowUpTickets;

/** Every five minutes in each academy: late first answers raised, resolved tickets closed after a quiet week. */
final class FollowUpTicketsCommand extends Command
{
    protected $signature = 'support:follow-up';

    protected $description = 'Raise tickets whose first answer is late and close resolved ones after a quiet week, in every academy';

    public function handle(EachTenant $each, FollowUpTickets $followUp): int
    {
        $changed = $each->sum(fn () => $followUp->handle());
        $this->info("{$changed} ticket(s) followed up.");

        return self::SUCCESS;
    }
}
