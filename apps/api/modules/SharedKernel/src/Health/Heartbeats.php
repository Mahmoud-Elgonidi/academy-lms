<?php

namespace Modules\SharedKernel\Health;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "I am still here" from the parts of the stack that run on their own: the
 * scheduler each minute, the queue worker for a job the scheduler sends it,
 * the live relay with its pulse. The platform's health page reads how long
 * ago each one last spoke (docs/architecture/03, section 12).
 */
final class Heartbeats
{
    private const PREFIX = 'health:beat:';

    /** @param  array<string, int|float|string>  $data */
    public static function beat(string $part, array $data = []): void
    {
        try {
            Cache::put(self::PREFIX.$part, ['at' => now()->getTimestamp()] + $data, now()->addDay());
        } catch (Throwable) {
            // A heartbeat never stops the part that beats: the health page shows the silence.
        }
    }

    /** @return array{at: int}&array<string, int|float|string>|null */
    public static function last(string $part): ?array
    {
        $beat = Cache::get(self::PREFIX.$part);

        return is_array($beat) && isset($beat['at']) ? $beat : null;
    }
}
