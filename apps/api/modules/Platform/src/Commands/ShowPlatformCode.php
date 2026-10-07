<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Models\PlatformAdmin;
use PragmaRX\Google2FA\Google2FA;

/** Local development only: the current authenticator code of a platform admin, to sign in to the demo console. */
final class ShowPlatformCode extends Command
{
    protected $signature = 'platform:code {email}';

    protected $description = 'Print a platform admin\'s current two-factor code (local only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Only in local development.');

            return self::FAILURE;
        }
        $secret = PlatformAdmin::query()->where('email', $this->argument('email'))->first()?->getAppAuthenticationSecret();
        if ($secret === null) {
            $this->error('No such admin, or no authenticator set up yet.');

            return self::FAILURE;
        }
        $this->line((new Google2FA)->getCurrentOtp($secret));

        return self::SUCCESS;
    }
}
