<?php

namespace Modules\Platform\Health;

use Modules\Platform\Models\PlatformAdmin;

/** Who hears when the stack breaks: the platform team's active console accounts, and HEALTH_ALERT_TO. */
final class AlertRecipients
{
    /** @return list<string> */
    public static function all(): array
    {
        return PlatformAdmin::query()->where('is_active', true)->pluck('email')
            ->merge(config('platform.health.alert_to'))->filter()->unique()->values()->all();
    }
}
