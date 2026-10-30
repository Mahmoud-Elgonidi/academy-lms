<?php

namespace Modules\Platform\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Time\OwnTimezone;
use Modules\SharedKernel\Time\Timezones;

/**
 * Someone of the platform team changes the zone the console shows them times
 * in (platform_admins.timezone), from the console's zone chip, and whether
 * the chip shows at all (platform_admins.zone_chip: hidden from its card,
 * shown again from the account menu).
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

    public function chipShown(Authenticatable $user): bool
    {
        return ! $user instanceof PlatformAdmin || $user->zone_chip !== false;
    }

    public function setChipShown(Authenticatable $user, bool $shown): void
    {
        if (! $user instanceof PlatformAdmin) {
            throw ValidationException::withMessages(['zone' => __('shared::chrome.zone.cannot')]);
        }
        $user->fill(['zone_chip' => $shown])->save();
    }

    public function chipComesBack(): string
    {
        return __('shared::chrome.zone.back_in_menu');
    }
}
