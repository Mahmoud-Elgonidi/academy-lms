<?php

namespace Modules\Identity\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\OwnTimezone;
use Modules\SharedKernel\Time\Timezones;

/**
 * Someone of an academy changes their own zone from the panels' zone chip:
 * the same change as My settings and PATCH me (UpdateProfile).
 */
final class SaveOwnTimezone implements OwnTimezone
{
    public function __construct(private readonly UpdateProfile $profile) {}

    public function save(Authenticatable $user, string $timezone): void
    {
        if (! $user instanceof User || ! Timezones::isValid($timezone)) {
            throw ValidationException::withMessages(['timezone' => __('shared::chrome.zone.invalid')]);
        }
        $this->profile->handle($user, ['timezone' => $timezone]);
    }
}
