<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Actions\Collections\FollowUpCollections;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every hour in each academy: the collection timeline's reminders and renewal previews, each once (J5a). */
final class FollowUpCollectionsCommand extends Command
{
    protected $signature = 'billing:follow-up';

    protected $description = 'Send the collection timeline\'s reminders and renewal previews in every academy';

    public function handle(EachTenant $each, FollowUpCollections $follow): int
    {
        $sent = $each->sum(fn () => $follow->handle());
        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
