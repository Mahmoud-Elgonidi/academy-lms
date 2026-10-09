<?php

namespace Modules\SharedKernel\Files;

use Illuminate\Support\Facades\Cache;
use Modules\SharedKernel\Logging\Redactor;
use Throwable;

/**
 * How scanning uploads goes, for the platform's health page: when a file
 * was last checked, when the scanner last could not answer (and why), and
 * how many files it refused. Kept a week in the cache.
 */
final class ScannerHealth
{
    private const KEY = 'health:scanner';

    public static function scanned(): void
    {
        self::put(fn (array $s) => ['scanned' => now()->getTimestamp()] + $s);
    }

    public static function failed(string $error): void
    {
        self::put(fn (array $s) => ['failed' => now()->getTimestamp(), 'error' => mb_substr(Redactor::text($error), 0, 300)] + $s);
    }

    public static function infected(): void
    {
        self::put(fn (array $s) => ['infected' => (int) ($s['infected'] ?? 0) + 1, 'scanned' => now()->getTimestamp()] + $s);
    }

    /** @return array{scanned?: int, failed?: int, error?: string, infected?: int} */
    public static function last(): array
    {
        $last = Cache::get(self::KEY);

        return is_array($last) ? $last : [];
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $change */
    private static function put(callable $change): void
    {
        try {
            Cache::put(self::KEY, $change(self::last()), now()->addWeek());
        } catch (Throwable) {
            // Noting how scanning went never stops an upload.
        }
    }
}
