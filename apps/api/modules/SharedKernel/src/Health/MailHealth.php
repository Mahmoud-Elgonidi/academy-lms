<?php

namespace Modules\SharedKernel\Health;

use Illuminate\Support\Facades\Cache;
use Modules\SharedKernel\Logging\Redactor;
use Throwable;

/**
 * Does email go out? The last message that left (any of them, from Laravel's
 * MessageSent) and the last one that could not, from whoever sent it: the
 * alerts, notifications, sign-in codes, a test. The platform's health page
 * says mail is down while the last attempt failed (docs/architecture/03,
 * section 12). Kept a week in the cache; the error is redacted like the logs.
 */
final class MailHealth
{
    private const KEY = 'health:mail';

    public static function sent(): void
    {
        self::put(['sent' => now()->getTimestamp()]);
    }

    public static function failed(Throwable $e): void
    {
        self::put(['failed' => now()->getTimestamp(), 'error' => mb_substr(Redactor::text($e->getMessage()), 0, 300)]);
    }

    /** @return array{sent?: int, failed?: int, error?: string} */
    public static function last(): array
    {
        $last = Cache::get(self::KEY);

        return is_array($last) ? $last : [];
    }

    /** The last attempt failed (and nothing left since). */
    public static function failing(): bool
    {
        $last = self::last();

        return ($last['failed'] ?? 0) > ($last['sent'] ?? 0);
    }

    /** @param  array<string, int|string>  $data */
    private static function put(array $data): void
    {
        try {
            Cache::put(self::KEY, $data + self::last(), now()->addWeek());
        } catch (Throwable) {
            // Noting how mail went never stops the mail.
        }
    }
}
