<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Str;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use SensitiveParameter;

/**
 * The demo console team (…@platform.test, an address that can never be a
 * real person's) takes the server's demo password, and its authenticator
 * key when one is given, after they changed in .env. Recovery codes go, and
 * so do their sessions (the console checks the password on each request).
 * Never a real admin.
 */
final class ResetDemoTeam
{
    public const DOMAIN = '@platform.test';

    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $secret): int
    {
        $admins = PlatformAdmin::query()->whereRaw('lower(email) like ?', ['%'.self::DOMAIN])->get();
        foreach ($admins as $admin) {
            $admin->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            if ($secret !== null) {
                $admin->saveAppAuthenticationSecret($secret);
                $admin->saveAppAuthenticationRecoveryCodes(null);
            }
            $this->audit->record(null, 'platform.demo_secrets_reset', 'platform_admin', $admin->id, ['name' => $admin->name]);
        }

        return $admins->count();
    }
}
