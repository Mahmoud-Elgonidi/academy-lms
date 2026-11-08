<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Auth\Access\AuthorizationException;
use Modules\Platform\Models\PlatformAdmin;

/** The AI engines, routes and comparisons are the platform's owners' alone, as Platform settings are. */
final class OwnersOnly
{
    public static function check(PlatformAdmin $by): void
    {
        if (! $by->is_owner || ! $by->is_active) {
            throw new AuthorizationException(__('platform::settings.owners_only'));
        }
    }
}
