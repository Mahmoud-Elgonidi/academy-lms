<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Makes someone of the platform team an owner, or no longer one
 * (`platform:admin {email} --owner` / `--no-owner`). Owners alone change
 * the platform's own settings (the services' keys in the console). Never
 * takes away the last owner who can still sign in, so someone always can.
 * Audited on the platform's log.
 */
final class SetPlatformAdminOwner
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(string $email, bool $owner): PlatformAdmin
    {
        return DB::connection('pgsql_platform')->transaction(function () use ($email, $owner) {
            $admin = PlatformAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->lockForUpdate()->first();
            if ($admin === null) {
                throw ValidationException::withMessages(['email' => __('platform::console.admins.unknown')]);
            }
            if ($admin->is_owner === $owner) {
                return $admin;
            }
            if ($owner && ! $admin->is_active) {
                throw ValidationException::withMessages(['email' => __('platform::console.admins.owner_locked')]);
            }
            if (! $owner && ! self::anotherActiveOwner($admin)) {
                throw ValidationException::withMessages(['email' => __('platform::console.admins.last_owner')]);
            }

            $admin->forceFill(['is_owner' => $owner])->save();
            $this->audit->record(null, $owner ? 'platform.admin_made_owner' : 'platform.admin_owner_removed', 'platform_admin', $admin->id, ['name' => $admin->name]);

            return $admin;
        });
    }

    /** Whether an owner other than $admin can still sign in (read under lock, inside the caller's transaction). */
    public static function anotherActiveOwner(PlatformAdmin $admin): bool
    {
        return PlatformAdmin::query()->where('is_owner', true)->where('is_active', true)->whereKeyNot($admin->id)->lockForUpdate()->exists();
    }
}
