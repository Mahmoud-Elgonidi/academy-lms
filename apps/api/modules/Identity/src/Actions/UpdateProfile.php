<?php

namespace Modules\Identity\Actions;

use Modules\Identity\Models\User;

/** The signed-in user changes their own name, language or timezone. */
final class UpdateProfile
{
    /** @param  array{name?: string, locale?: string, timezone?: string}  $changes */
    public function handle(User $user, array $changes): User
    {
        $user->fill(array_intersect_key($changes, array_flip(['name', 'locale', 'timezone'])))->save();

        return $user;
    }
}
