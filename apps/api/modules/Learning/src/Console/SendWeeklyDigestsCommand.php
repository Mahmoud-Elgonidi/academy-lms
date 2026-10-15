<?php

namespace Modules\Learning\Console;

use Illuminate\Console\Command;
use Modules\Learning\Actions\Digest\QueueWeeklyDigests;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every hour in each academy that has it on: the weekly notes whose Friday evening has come on the learner's clock. */
final class SendWeeklyDigestsCommand extends Command
{
    protected $signature = 'learning:digests';

    protected $description = 'Send each learner\'s weekly note to their guardians on Friday evening, their own clock';

    public function handle(EachTenant $each, QueueWeeklyDigests $queue): int
    {
        $queued = $each->sum(fn () => $queue->handle());
        $this->info("{$queued} weekly note(s) on their way.");

        return self::SUCCESS;
    }
}
