<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Locks someone of the platform team out of the console, or lets them back
 * in (`platform:admin --deactivate` / `--reactivate`). Locked, they cannot
 * sign in, the console signs out any session they still have on its next
 * request (ConsoleContext), a "remember me" cookie no longer works, and a
 * sign-in as someone of an academy they started ends (WatchImpersonation).
 * Never the last one who can still sign in, so the console always has
 * someone. Audited on the platform's log.
 */
final class SetPlatformAdminActive
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(string $email, bool $active): PlatformAdmin
    {
        return DB::connection('pgsql_platform')->transaction(function () use ($email, $active) {
            $admin = PlatformAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->lockForUpdate()->first();
            if ($admin === null) {
                throw ValidationException::withMessages(['email' => __('platform::console.admins.unknown')]);
            }
            if ($admin->is_active === $active) {
                return $admin;
            }
            if (! $active && ! PlatformAdmin::query()->where('is_active', true)->whereKeyNot($admin->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => __('platform::console.admins.last')]);
            }

            $admin->forceFill(['is_active' => $active, 'remember_token' => Str::random(60)])->save();
            $this->audit->record(null, $active ? 'platform.admin_reactivated' : 'platform.admin_deactivated', 'platform_admin', $admin->id, ['name' => $admin->name]);

            return $admin;
        });
    }
}
