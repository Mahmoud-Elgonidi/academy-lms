<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Models\Site\SignupRequest;

/**
 * Signup requests that never became an academy leave a year after they came:
 * the platform keeps a prospect's name, email and number only as long as it
 * may still answer them.
 */
final class SweepSignupRequests extends Command
{
    public const KEEP_MONTHS = 12;

    protected $signature = 'platform:site-sweep';

    protected $description = 'Remove signup requests from the platform\'s page that did not become an academy within a year';

    public function handle(): int
    {
        $gone = SignupRequest::query()->where('status', '<>', 'created')->where('created_at', '<', now()->subMonths(self::KEEP_MONTHS))->delete();
        $this->line("removed {$gone} old signup request(s)");

        return self::SUCCESS;
    }
}
