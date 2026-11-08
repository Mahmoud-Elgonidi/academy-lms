<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * Which balance goes first: the month's allowance (it ends with the month
 * and is never paid for), then the bought and granted lots, the one ending
 * soonest first (FIFO by expiry, then by when it came), so paid balance is
 * never wasted while free balance is left.
 */
final class SpendOrder
{
    /**
     * @param  list<Lot>  $lots
     * @return list<Lot> the lots still live at $at with something left, in the order they are drawn
     */
    public static function of(array $lots, CarbonImmutable $at): array
    {
        $live = array_values(array_filter($lots, fn (Lot $l) => $l->remaining > 0 && $l->liveAt($at)));
        usort($live, fn (Lot $a, Lot $b) => [$a->isAllowance() ? 0 : 1, $a->expiresAt->getTimestamp(), $a->createdAt->getTimestamp(), $a->id]
            <=> [$b->isAllowance() ? 0 : 1, $b->expiresAt->getTimestamp(), $b->createdAt->getTimestamp(), $b->id]);

        return $live;
    }
}
