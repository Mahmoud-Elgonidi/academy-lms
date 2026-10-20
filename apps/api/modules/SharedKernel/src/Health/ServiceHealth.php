<?php

namespace Modules\SharedKernel\Health;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * How each outside service answers (docs/architecture/03, section 12): the AI
 * providers, speech to text, WhatsApp, texts, push, LiveKit, Stripe. Their
 * gateways write here when a call works or fails and why (a ServiceProblem
 * from Health\Faults\*, the provider's code only, never its words); the
 * console's "Try it" and the daily `platform:services` write here too. The
 * platform's health page, its alert mail and the settings page read it.
 *
 * One cache entry per service, kept thirty days. A failure is cleared by a
 * success of the same scope (a model, a template), by one with no scope, or
 * by a check, so a working fast model does not hide a retired best one.
 */
final class ServiceHealth
{
    private const KEY = 'health:service:';

    private const DAYS = 30;

    /** A success is written at most once a minute while nothing is wrong. */
    private const QUIET = 60;

    public static function ok(string $service, string $scope = '', string $via = 'use'): void
    {
        $now = now()->getTimestamp();
        $last = self::last($service);
        $failing = self::failingIn($last);
        $clears = $via === 'check' || $scope === '' || ($last['scope'] ?? '') === '' || ($last['scope'] ?? '') === $scope;

        if (! $failing) {
            if ($via !== 'check' && $now - (int) ($last['ok'] ?? 0) < self::QUIET) {
                return;
            }
            self::put($service, ['ok' => $now, 'seen' => $now] + ($via === 'check' ? ['checked' => $now] : []) + $last);

            return;
        }
        if (! $clears) {
            self::put($service, ['seen' => $now] + $last);

            return;
        }
        $kept = array_diff_key($last, array_flip(['problem', 'code', 'scope', 'since', 'in_a_row']));
        self::put($service, ['ok' => $now, 'seen' => $now, 'in_a_row' => 0] + ($via === 'check' ? ['checked' => $now] : []) + $kept);
    }

    public static function failed(string $service, ServiceProblem $problem, string $code = '', string $scope = '', string $via = 'use'): void
    {
        $now = now()->getTimestamp();
        $last = self::last($service);
        $failing = self::failingIn($last);

        self::put($service, [
            'failed' => $now,
            'problem' => $problem->value,
            'code' => self::clean($code),
            'scope' => self::clean($scope),
            'via' => $via,
            'in_a_row' => $failing ? (int) ($last['in_a_row'] ?? 0) + 1 : 1,
            'since' => $failing ? (int) ($last['since'] ?? $now) : $now,
        ] + ($via === 'check' ? ['checked' => $now] : []) + $last);
    }

    public static function fault(string $service, ServiceFault $fault, string $scope = '', string $via = 'use'): void
    {
        self::failed($service, $fault->problem, $fault->code, $scope, $via);
    }

    /** How much of the plan's credit is used, from a check (null: nothing to say). */
    public static function near(string $service, ?int $percent): void
    {
        $last = self::last($service);
        if ($percent === null) {
            unset($last['near'], $last['near_at']);
            self::put($service, $last);

            return;
        }
        self::put($service, ['near' => max(0, min(100, $percent)), 'near_at' => now()->getTimestamp()] + $last);
    }

    /** A check ran (whatever it found). */
    public static function checked(string $service): void
    {
        self::put($service, ['checked' => now()->getTimestamp()] + self::last($service));
    }

    /**
     * @return array{ok?: int, seen?: int, failed?: int, problem?: string, code?: string, scope?: string, via?: string,
     *     in_a_row?: int, since?: int, near?: int, near_at?: int, checked?: int}
     */
    public static function last(string $service): array
    {
        try {
            $last = Cache::get(self::KEY.$service);
        } catch (Throwable) {
            return [];
        }

        return is_array($last) ? $last : [];
    }

    /** The last word from it was a failure (and no success since cleared it). */
    public static function failing(string $service): bool
    {
        return self::failingIn(self::last($service));
    }

    /** The problem while failing. */
    public static function problem(string $service): ?ServiceProblem
    {
        $last = self::last($service);

        return self::failingIn($last) ? ServiceProblem::tryFrom((string) ($last['problem'] ?? '')) : null;
    }

    public static function forget(string $service): void
    {
        try {
            Cache::forget(self::KEY.$service);
        } catch (Throwable) {
            // Nothing to forget then.
        }
    }

    /** @param  array<string, mixed>  $last */
    private static function failingIn(array $last): bool
    {
        return isset($last['problem']) && (int) ($last['failed'] ?? 0) >= (int) ($last['ok'] ?? 0);
    }

    /** A provider's code or a scope: letters, digits and a few signs, never a sentence. */
    private static function clean(string $text): string
    {
        return mb_substr(trim((string) preg_replace('/[^A-Za-z0-9_.:\- ]/', '', $text)), 0, 60);
    }

    /** @param  array<string, mixed>  $data */
    private static function put(string $service, array $data): void
    {
        try {
            Cache::put(self::KEY.$service, $data, now()->addDays(self::DAYS));
        } catch (Throwable) {
            // Noting how a service answered never stops the call.
        }
    }
}
