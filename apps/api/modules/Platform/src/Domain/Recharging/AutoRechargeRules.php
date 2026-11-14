<?php

namespace Modules\Platform\Domain\Recharging;

use Carbon\CarbonImmutable;

/**
 * When an automatic recharge buys (docs/architecture/02, section 11, "الشحن
 * التلقائي"), Twilio's way: while the balance is below the threshold, buy the
 * pack, at most so many times a calendar month. A soft decline is tried once
 * more 24 hours later; a hard one, or the retry declined too, turns it off.
 * Pure: it is handed what the ledger and the purchases say.
 */
final class AutoRechargeRules
{
    public const RETRY_HOURS = 24;

    public const CAP_MAX = 50;

    public const WAIT = 'wait';

    public const BUY = 'buy';

    public const RETRY = 'retry';

    public const NOTHING = 'nothing';

    public const CAPPED = 'capped';

    /**
     * What to do now.
     *
     * @param  ?array{status: string, attempt: int, soft: bool, failed_at: ?CarbonImmutable, retried: bool}  $last  the last automatic purchase this month for the meter
     */
    public static function next(int $available, int $threshold, int $boughtThisMonth, int $cap, ?array $last, CarbonImmutable $now): string
    {
        if ($last !== null && $last['status'] === 'open') {
            return self::WAIT;    // the last one is not answered yet
        }
        if ($available >= $threshold) {
            return self::NOTHING;
        }
        if ($last !== null && $last['status'] === 'failed' && $last['attempt'] === 1 && $last['soft'] && ! $last['retried']) {
            return $last['failed_at'] !== null && $now->greaterThanOrEqualTo($last['failed_at']->addHours(self::RETRY_HOURS)) ? self::RETRY : self::WAIT;
        }
        if ($boughtThisMonth >= $cap) {
            return self::CAPPED;
        }

        return self::BUY;
    }

    /** After a decline: whether automatic recharge stays on (a soft first decline waits for its retry). */
    public static function staysOn(int $attempt, bool $soft): bool
    {
        return $attempt === 1 && $soft;
    }
}
