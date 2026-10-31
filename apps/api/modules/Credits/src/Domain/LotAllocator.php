<?php

namespace Modules\Credits\Domain;

use Carbon\CarbonImmutable;

/**
 * Which lots pay for a session: only lots still valid when the session
 * starts, the soonest to expire first (so minutes that are about to lapse
 * get used), oldest first among equals. A session may draw on several lots.
 * Makeup minutes are kept for makeup sessions, which use nothing else; a
 * makeup lot kept for private or group makeup pays only for that kind.
 * All or nothing: if the valid lots cannot cover it, nothing is taken.
 */
final class LotAllocator
{
    public const MAKEUP = 'makeup';

    /** A makeup lesson with the course's own teacher. */
    public const PRIVATE = 'private';

    /** A makeup lesson in a free seat of another group's lesson. */
    public const GROUP = 'group';

    /**
     * @param  list<LotBalance>  $lots
     * @return array<string, int>|null lot id => minutes, or null when there is not enough
     */
    public static function allocate(array $lots, int $minutes, CarbonImmutable $usableAt, bool $forMakeup = false, string $makeupFor = self::PRIVATE): ?array
    {
        $usable = array_values(array_filter(
            $lots,
            fn (LotBalance $lot) => $lot->remainingMin > 0
                && ($lot->expiresAt === null || $lot->expiresAt->greaterThan($usableAt))
                && ($lot->source === self::MAKEUP) === $forMakeup
                && (! $forMakeup || $lot->useFor === null || $lot->useFor === $makeupFor),
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
