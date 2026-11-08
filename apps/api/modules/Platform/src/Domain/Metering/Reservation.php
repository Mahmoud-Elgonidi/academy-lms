<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * Reserve, then settle (docs/architecture/02, section 11): before a request
 * the estimate is held, so two requests at once cannot both take the last
 * credits; after it, the actual is taken and the hold released; a request
 * that failed or timed out costs nothing. A hold nobody settled lets go by
 * itself after HOLD_MINUTES (and a sweep writes its release).
 */
final class Reservation
{
    public const HOLD_MINUTES = 30;

    /** @param  list<Hold>  $holds */
    public static function held(array $holds, CarbonImmutable $now): int
    {
        $held = 0;
        foreach ($holds as $hold) {
            if (! $hold->released && ! self::isStale($hold, $now)) {
                $held += $hold->amount;
            }
        }

        return $held;
    }

    /**
     * @param  list<Hold>  $holds
     * @return list<string> the holds nobody settled in time
     */
    public static function stale(array $holds, CarbonImmutable $now): array
    {
        return array_values(array_map(fn (Hold $h) => $h->id, array_filter($holds, fn (Hold $h) => ! $h->released && self::isStale($h, $now))));
    }

    /** Whether a request of $estimate may start: always without a limit, else only when it fits what is left. */
    public static function fits(int $available, int $estimate, bool $limited): bool
    {
        return ! $limited || $estimate <= $available;
    }

    private static function isStale(Hold $hold, CarbonImmutable $now): bool
    {
        return $hold->createdAt->addMinutes(self::HOLD_MINUTES)->lessThanOrEqualTo($now);
    }
}
