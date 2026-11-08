<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The rules of an academy's usage ledger (docs/architecture/02, section 11):
 * one append-only list of entries per academy and meter. A lot (the month's
 * allowance, a purchase, a bonus, a positive adjustment) is a positive
 * entry; a use, an expiry, a refund or a negative adjustment draws from lots
 * in spend order; what no lot covers is overage, and the next lot covers it
 * first. A request under way holds its estimate (reserve) until it is
 * settled or released. The balance is always worked out from the entries.
 */
final class Ledger
{
    public const ALLOWANCE = 'allowance';

    public const PURCHASE = 'purchase';

    public const BONUS = 'bonus';

    public const CONSUME = 'consume';

    public const RESERVE = 'reserve';

    public const RELEASE = 'release';

    public const EXPIRE = 'expire';

    public const REFUND = 'refund';

    public const ADJUST = 'adjust';

    public const KINDS = [self::ALLOWANCE, self::PURCHASE, self::BONUS, self::CONSUME, self::RESERVE, self::RELEASE, self::EXPIRE, self::REFUND, self::ADJUST];

    /** The kinds that open a lot (an adjustment only when it adds). */
    public const LOT_KINDS = [self::ALLOWANCE, self::PURCHASE, self::BONUS, self::ADJUST];

    /** Bought balance lasts this long; a bonus too, unless the platform team says otherwise. */
    public const LOT_MONTHS = 12;

    /**
     * Takes $amount from the lots in spend order; what they cannot cover is overage.
     *
     * @param  list<Lot>  $lots
     */
    public static function draw(array $lots, int $amount, CarbonImmutable $at): Draw
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('A use draws a positive amount.');
        }
        $from = [];
        $left = $amount;
        foreach (SpendOrder::of($lots, $at) as $lot) {
            if ($left === 0) {
                break;
            }
            $take = min($left, $lot->remaining);
            $from[$lot->id] = $take;
            $left -= $take;
        }

        return new Draw($from, $left);
    }

    /** How much of the overage owed a new lot of $amount covers at once. */
    public static function cover(int $debt, int $amount): int
    {
        return max(0, min($debt, $amount));
    }

    /**
     * The balance at $at: the allowance and the other lots still live, less
     * the overage and what is held.
     *
     * @param  list<Lot>  $lots
     */
    public static function balance(array $lots, int $debt, int $held, CarbonImmutable $at): Balance
    {
        $allowance = 0;
        $bought = 0;
        foreach ($lots as $lot) {
            if (! $lot->liveAt($at) || $lot->remaining <= 0) {
                continue;
            }
            $lot->isAllowance() ? $allowance += $lot->remaining : $bought += $lot->remaining;
        }

        return new Balance($allowance, $bought, max(0, $debt), max(0, $held));
    }

    /**
     * The balance after each entry, in the order given (the statement): holds
     * and uses counted while the plan had no limit leave it as it was.
     *
     * @param  list<Entry>  $entries  oldest first
     * @return list<int>
     */
    public static function runningBalance(array $entries, int $opening = 0): array
    {
        $out = [];
        $balance = $opening;
        foreach ($entries as $entry) {
            if ($entry->counts()) {
                $balance += $entry->amount;
            }
            $out[] = $balance;
        }

        return $out;
    }

    /** Whether an amount of this kind has the sign it must (a lot adds; a use, an expiry or a refund takes). */
    public static function signFits(string $kind, int $amount, bool $coveringOverage = false): bool
    {
        return match ($kind) {
            self::ALLOWANCE, self::PURCHASE, self::BONUS, self::RELEASE => $amount > 0,
            self::RESERVE, self::EXPIRE, self::REFUND => $amount < 0,
            self::CONSUME => $coveringOverage ? $amount !== 0 : $amount < 0,
            self::ADJUST => $amount !== 0,
            default => false,
        };
    }
}
