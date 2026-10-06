<?php

namespace Modules\Credits\Domain;

use Carbon\CarbonImmutable;

/**
 * Which lots pay for a session: only lots still valid when the session
 * starts, the soonest to expire first (so minutes that are about to lapse
 * get used), oldest first among equals. A session may draw on several lots.
 * Makeup minutes are kept for makeup sessions, which use nothing else.
 * All or nothing: if the valid lots cannot cover it, nothing is taken.
 */
final class LotAllocator
{
    public const MAKEUP = 'makeup';

    /**
     * @param  list<LotBalance>  $lots
     * @return array<string, int>|null lot id => minutes, or null when there is not enough
     */
    public static function allocate(array $lots, int $minutes, CarbonImmutable $usableAt, bool $forMakeup = false): ?array
    {
        $usable = array_values(array_filter(
            $lots,
            fn (LotBalance $lot) => $lot->remainingMin > 0
                && ($lot->expiresAt === null || $lot->expiresAt->greaterThan($usableAt))
                && ($lot->source === self::MAKEUP) === $forMakeup,
        ));

        usort($usable, fn (LotBalance $a, LotBalance $b) => [$a->expiresAt === null, $a->expiresAt?->getTimestamp(), $a->createdAt->getTimestamp(), $a->id]
            <=> [$b->expiresAt === null, $b->expiresAt?->getTimestamp(), $b->createdAt->getTimestamp(), $b->id]);

        $allocations = [];
        $left = $minutes;
        foreach ($usable as $lot) {
            if ($left === 0) {
                break;
            }
            $take = min($left, $lot->remainingMin);
            $allocations[$lot->id] = $take;
            $left -= $take;
        }

        return $left === 0 ? $allocations : null;
    }
}
