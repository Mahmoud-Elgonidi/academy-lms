<?php

namespace Modules\Identity\Commands;

use Illuminate\Console\Command;
use Modules\Identity\Actions\ResetDemoPasswords;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Tenancy\EachTenant;

/**
 * After DEMO_PASSWORD and DEMO_PLATFORM_2FA_SECRET change in a server's .env
 * (say they were shown to someone), the demo accounts take the new ones:
 * every …@demo.test account in each active academy, and the demo console
 * team (…@platform.test), whose recovery codes go too. Their sign-ins end.
 * Addresses on .test are never a real person's, so no real account changes
 * (infra/deploy/RUNBOOKS.md, "Changing a secret").
 */
final class RotateDemoSecrets extends Command
{
    protected $signature = 'identity:demo-secrets';

    protected $description = 'Give the demo accounts the DEMO_PASSWORD and DEMO_PLATFORM_2FA_SECRET now in .env, ending their sign-ins';

    public function handle(EachTenant $each, ResetDemoPasswords $reset, PlatformApi $platform): int
    {
        $password = (string) config('app.demo.password');
        $secret = strtoupper(str_replace(' ', '', (string) config('app.demo.platform_2fa_secret')));
        if ($password === '' || (! app()->environment('local', 'testing') && strlen($password) < 12)) {
            $this->error('Put a new DEMO_PASSWORD in .env first (12 characters or more), then `docker compose up -d`.');

            return self::FAILURE;
        }
        if ($secret !== '' && preg_match('/^[A-Z2-7]{16,}$/', $secret) !== 1) {
            $this->error('DEMO_PLATFORM_2FA_SECRET must be base32: 16 or more of A-Z and 2-7.');

            return self::FAILURE;
        }

        $users = $each->sum(fn () => $reset->handle($password));
        $admins = $platform->resetDemoTeam($password, $secret === '' ? null : $secret);

        $this->info("{$users} demo accounts and {$admins} demo console accounts now use the server's DEMO_PASSWORD"
            .($secret === '' ? '' : ' and DEMO_PLATFORM_2FA_SECRET').'; their sign-ins ended.');

        return self::SUCCESS;
    }
}
