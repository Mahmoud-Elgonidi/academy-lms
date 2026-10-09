<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Models\PlatformAdmin;
use PragmaRX\Google2FA\Google2FA;

/**
 * The current authenticator code of a platform admin, to sign in to the demo
 * console: anyone's in local development, and on a server only the demo
 * admin's, whose key is the server's own DEMO_PLATFORM_2FA_SECRET (whoever can
 * run this there can read it in .env anyway). Never a real admin's.
 */
final class ShowPlatformCode extends Command
{
    protected $signature = 'platform:code {email}';

    protected $description = 'Print a platform admin\'s current two-factor code (local, or the demo admin on a server)';

    public function handle(): int
    {
        $secret = PlatformAdmin::query()->where('email', $this->argument('email'))->first()?->getAppAuthenticationSecret();
        if ($secret === null) {
            $this->error('No such admin, or no authenticator set up yet.');

            return self::FAILURE;
        }
        $demo = (string) config('app.demo.platform_2fa_secret');
        if (! app()->environment('local', 'testing') && ($demo === '' || ! hash_equals($demo, $secret))) {
            $this->error('Only in local development, or for the demo admin (DEMO_PLATFORM_2FA_SECRET).');

            return self::FAILURE;
        }
        $this->line((new Google2FA)->getCurrentOtp($secret));

        return self::SUCCESS;
    }
}
