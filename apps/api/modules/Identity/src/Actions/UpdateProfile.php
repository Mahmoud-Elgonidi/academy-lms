<?php

namespace Modules\Identity\Actions;

use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\TimeFormat;

/**
 * The signed-in user changes their own name, language, timezone or how they
 * read a clock (12 or 24 hours; none follows the academy's choice).
 */
final class UpdateProfile
{
    /** @param  array{name?: string, locale?: string, timezone?: string, time_format?: ?string}  $changes */
    public function handle(User $user, array $changes): User
    {
        $changes = array_intersect_key($changes, array_flip(['name', 'locale', 'timezone', 'time_format']));
        if (array_key_exists('time_format', $changes)) {
            $changes['time_format'] = TimeFormat::of($changes['time_format'])?->value;
        }
        $user->fill($changes)->save();

        return $user;
    }
}
