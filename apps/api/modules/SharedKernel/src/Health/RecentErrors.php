<?php

namespace Modules\SharedKernel\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Modules\SharedKernel\Logging\Redactor;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * The errors the app reported lately, for the platform's health page: one
 * entry per kind (class and place), how many times, first and last seen, the
 * last message (redacted like the logs), the page or command it came from
 * and the academy. Counted by the hour too. Kept a week in the cache; the
 * logs have the rest.
 */
final class RecentErrors
{
    private const KEY = 'health:errors';

    private const KEEP = 50;

    public static function record(Throwable $e): void
    {
        try {
            $place = self::place($e);
            $id = substr(sha1($e::class.'|'.$place), 0, 16);
            $now = now()->getTimestamp();
            $errors = self::all();
            $seen = $errors[$id] ?? ['count' => 0, 'first' => $now];
            $errors[$id] = [
                'id' => $id,
                'class' => $e::class,
                'message' => mb_substr(Redactor::text($e->getMessage()), 0, 300),
                'place' => $place,
                'where' => app()->runningInConsole() ? 'console' : request()->method().' /'.ltrim(request()->path(), '/'),
                'academy' => Context::get(TenantContext::CONTEXT_KEY),
                'count' => $seen['count'] + 1,
                'first' => $seen['first'],
                'last' => $now,
            ];
            uasort($errors, fn (array $a, array $b) => $b['last'] <=> $a['last']);
            Cache::put(self::KEY, array_slice($errors, 0, self::KEEP, true), now()->addWeek());

            $hour = self::KEY.':'.gmdate('YmdH', $now);
            Cache::add($hour, 0, now()->addDays(2));
            Cache::increment($hour);
        } catch (Throwable) {
            // Recording an error never adds one.
        }
    }

    /** @return array<string, array{id: string, class: string, message: string, place: string, where: string, academy: ?string, count: int, first: int, last: int}> newest first */
    public static function all(): array
    {
        $errors = Cache::get(self::KEY);

        return is_array($errors) ? $errors : [];
    }

    /** How many errors in the last $hours hours (whole hours, this one included). */
    public static function countLast(int $hours): int
    {
        $total = 0;
        for ($i = 0; $i < $hours; $i++) {
            $total += (int) Cache::get(self::KEY.':'.gmdate('YmdH', now()->getTimestamp() - $i * 3600), 0);
        }

        return $total;
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /** Where it was thrown, in the app's own code when there is a frame there. */
    private static function place(Throwable $e): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        foreach ([['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()] as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file !== '' && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                return str_replace($base, '', $file).':'.($frame['line'] ?? 0);
            }
        }

        return str_replace($base, '', $e->getFile()).':'.$e->getLine();
    }
}
