<?php

namespace Modules\SharedKernel\Time;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Saves the zone someone chose for themselves, for the module that owns
 * their account (Identity's users, Platform's console team), so screens of
 * every panel (the zone chip) change it without knowing whose account it is.
 * Refuses with a ValidationException when the change may not be made.
 */
interface OwnTimezone
{
    public function save(Authenticatable $user, string $timezone): void;
}
