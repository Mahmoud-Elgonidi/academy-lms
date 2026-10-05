<?php

namespace Modules\SharedKernel\Time;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Turns a wall-clock date and time in a timezone ("Saturday 19:00 in Cairo")
 * into the UTC instant it means, following the timezone database.
 *
 * Summer-time edges (docs/architecture/02-domain-rules.md, section 1):
 *  - a time that does not exist (clocks jump forward) moves later by the gap;
 *  - a time that happens twice (clocks go back) takes the earlier occurrence.
 * The result says which case applied, so callers can flag it.
 */
final class LocalTimeResolver
{
    public function resolve(string $localDate, string $localTime, string $timezone): ResolvedLocalTime
    {
        if (! Timezones::isValid($timezone)) {
            throw new InvalidArgumentException("Unknown timezone [{$timezone}].");
        }

        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$localDate} {$localTime}", new DateTimeZone('UTC'));
        if ($wall === false) {
            throw new InvalidArgumentException("Bad local date/time [{$localDate} {$localTime}].");
        }

        $zone = new DateTimeZone($timezone);
        // The wall clock read as if it were UTC; subtracting the zone's offset gives the instant.
        $wallSeconds = $wall->getTimestamp();

        $candidates = [];
        foreach ($this->offsetsAround($zone, $wallSeconds) as $offset) {
            $instant = $wallSeconds - $offset;
            if ($zone->getOffset(new DateTimeImmutable('@'.$instant)) === $offset) {
                $candidates[] = $instant;
            }
        }

        if ($candidates !== []) {
            sort($candidates);

            return new ResolvedLocalTime(
                CarbonImmutable::createFromTimestamp($candidates[0], 'UTC'),
                count($candidates) > 1 ? LocalTimeKind::Ambiguous : LocalTimeKind::Exact,
            );
        }

        // In a gap: read the time with the offset from before the jump, which lands it
        // after the jump, later by exactly the gap.
        $before = $zone->getOffset(new DateTimeImmutable('@'.($wallSeconds - 86400)));
        $instant = $wallSeconds - $before;
        $after = $zone->getOffset(new DateTimeImmutable('@'.$instant));

        return new ResolvedLocalTime(
            CarbonImmutable::createFromTimestamp($instant, 'UTC'),
            LocalTimeKind::ShiftedForward,
            intdiv($after - $before, 60),
        );
    }

    /** @return list<int> Every UTC offset the zone uses within two days of the given wall time. */
    private function offsetsAround(DateTimeZone $zone, int $wallSeconds): array
    {
        $transitions = $zone->getTransitions($wallSeconds - 2 * 86400, $wallSeconds + 2 * 86400) ?: [];

        return array_values(array_unique(array_map(fn (array $t) => (int) $t['offset'], $transitions)));
    }
}
