<?php

namespace Modules\SharedKernel\Time;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Saves the zone someone chose for themselves, for the module that owns
 * their account (Identity's users, Platform's console team), so screens of
 * every panel (the zone chip) change it without knowing whose account it is.
 * Refuses with a ValidationException when the change may not be made.
 *
 * It also keeps whether the person shows the zone chip at all: hidden from
 * the chip's own card, brought back where the account's settings are.
 */
interface OwnTimezone
{
    public function save(Authenticatable $user, string $timezone): void;

    /** Whether the person shows the zone chip (shown unless they hid it). */
    public function chipShown(Authenticatable $user): bool;

    public function setChipShown(Authenticatable $user, bool $shown): void;

    /** Where the person brings a hidden chip back, in the reader's words ("You can show it again from My settings."). */
    public function chipComesBack(): string;
}
