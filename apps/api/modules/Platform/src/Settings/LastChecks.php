<?php

namespace Modules\Platform\Settings;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The last "Try it" of each Platform settings section, kept thirty days so
 * the page shows it under the section's title: what it found, when, and who
 * tried (the daily check tries as nobody). Lines are wording keys, never a
 * provider's words or a key.
 */
final class LastChecks
{
    private const KEY = 'platform:check:';

    public static function put(string $group, CheckResult $result, ?string $by): void
    {
        try {
            Cache::put(self::KEY.$group, $result->toArray() + ['at' => now()->getTimestamp(), 'by' => $by], now()->addDays(30));
        } catch (Throwable) {
            // Not kept: the next try shows again.
        }
    }

    /** @return ?array{result: CheckResult, at: int, by: ?string} */
    public static function get(string $group): ?array
    {
        try {
            $kept = Cache::get(self::KEY.$group);
        } catch (Throwable) {
            return null;
        }

        return is_array($kept) ? ['result' => CheckResult::from($kept), 'at' => (int) ($kept['at'] ?? 0), 'by' => $kept['by'] ?? null] : null;
    }
}
