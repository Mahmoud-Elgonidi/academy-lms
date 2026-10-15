<?php

namespace Modules\Platform\Ai;

use Illuminate\Support\Facades\Cache;
use Modules\SharedKernel\Logging\Redactor;
use Throwable;

/**
 * How the AI providers answer, for the platform's health page: when each
 * last answered, when one last failed (and why, redacted), and how many
 * failures in a row. Kept a week in the cache.
 */
final class AiHealth
{
    private const KEY = 'health:ai';

    public static function ok(string $provider): void
    {
        self::change($provider, fn (array $p) => ['ok' => now()->getTimestamp(), 'in_a_row' => 0] + $p);
    }

    public static function failed(string $provider, string $error): void
    {
        self::change($provider, fn (array $p) => [
            'failed' => now()->getTimestamp(),
            'error' => mb_substr(Redactor::text($error), 0, 300),
            'in_a_row' => (int) ($p['in_a_row'] ?? 0) + 1,
        ] + $p);
    }

    /** @return array<string, array{ok?: int, failed?: int, error?: string, in_a_row?: int}> by provider */
    public static function last(): array
    {
        $last = Cache::get(self::KEY);

        return is_array($last) ? $last : [];
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $change */
    private static function change(string $provider, callable $change): void
    {
        try {
            $all = self::last();
            $all[$provider] = $change($all[$provider] ?? []);
            Cache::put(self::KEY, $all, now()->addWeek());
        } catch (Throwable) {
            // Noting how a provider answered never stops a helper.
        }
    }
}
