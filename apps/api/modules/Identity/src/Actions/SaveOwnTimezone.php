<?php

namespace Modules\Identity\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\PreferenceStore;
use Modules\Identity\Preferences\ZoneChipPreference;
use Modules\SharedKernel\Time\OwnTimezone;
use Modules\SharedKernel\Time\Timezones;

/**
 * Someone of an academy changes their own zone from the panels' zone chip:
 * the same change as My settings and PATCH me (UpdateProfile). Hiding the
 * chip is their `zone_chip` preference (UpdatePreferences), as My settings
 * and PATCH me save it.
 */
final class SaveOwnTimezone implements OwnTimezone
{
    public function __construct(
        private readonly UpdateProfile $profile,
        private readonly UpdatePreferences $preferences,
        private readonly PreferenceStore $store,
    ) {}

    public function save(Authenticatable $user, string $timezone): void
    {
        if (! $user instanceof User || ! Timezones::isValid($timezone)) {
            throw ValidationException::withMessages(['timezone' => __('shared::chrome.zone.invalid')]);
        }
        $this->profile->handle($user, ['timezone' => $timezone]);
    }

    public function chipShown(Authenticatable $user): bool
    {
        return ! $user instanceof User || $this->store->valueFor($user, ZoneChipPreference::KEY) === ZoneChipPreference::SHOWN;
    }

    public function setChipShown(Authenticatable $user, bool $shown): void
    {
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['zone' => __('shared::chrome.zone.cannot')]);
        }
        $this->preferences->handle($user, [ZoneChipPreference::KEY => ZoneChipPreference::value($shown)]);
    }

    public function chipComesBack(): string
    {
        return __('shared::chrome.zone.back_in_settings');
    }
}
