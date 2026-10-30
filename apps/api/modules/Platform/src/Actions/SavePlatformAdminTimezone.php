<?php

namespace Modules\Platform\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Time\OwnTimezone;
use Modules\SharedKernel\Time\Timezones;

/**
 * Someone of the platform team changes the zone the console shows them times
 * in (platform_admins.timezone), from the console's zone chip.
 */
final class SavePlatformAdminTimezone implements OwnTimezone
{
    public function save(Authenticatable $user, string $timezone): void
    {
        if (! $user instanceof PlatformAdmin || ! Timezones::isValid($timezone)) {
            throw ValidationException::withMessages(['timezone' => __('shared::chrome.zone.invalid')]);
        }
        $user->fill(['timezone' => $timezone])->save();
    }
}
