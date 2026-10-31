<?php

namespace Modules\Billing\Domain;

use InvalidArgumentException;

/**
 * What comes off an invoice (docs/architecture/02, section 3, "الخصومات"):
 * the family discount for a second or later child, and a coupon. A coupon
 * either adds to the family discount (taken from what is left after it) or,
 * by default, the bigger of the two applies; on a tie the family discount
 * does, so a coupon is not used up for nothing. Rounded half up per line,
 * and never more than the price. A course that joins a group after it
 * began has the lessons it missed taken off its first block first (the
 * block's lesson price each, at most the block); the family discount and
 * the coupon then apply to what is left.
 */
final class Discounts
{
    /**
     * @param  array{0: int, 1: int}|null  $lateJoin  [lessons missed, the block's lessons]: what a late join takes off first
     * @return list<Discount> in the order they are taken off
     */
    public static function on(int $baseMinor, int $siblingBp, ?CouponTerms $coupon, ?array $lateJoin = null): array
    {
        if ($baseMinor < 0 || $siblingBp < 0 || $siblingBp > 10_000) {
            throw new InvalidArgumentException('A price and a rate are not negative, and a rate is at most 100%.');
        }
        $late = $lateJoin === null ? null : self::lateJoin($baseMinor, $lateJoin[0], $lateJoin[1]);
        if ($late !== null) {
            return [$late, ...self::on($baseMinor - $late->minor, $siblingBp, $coupon)];
        }
        $sibling = $siblingBp > 0 ? new Discount(Discount::SIBLING, self::percent($baseMinor, $siblingBp), $siblingBp) : null;
        if ($sibling !== null && $sibling->minor === 0) {
            $sibling = null;
        }
        if ($coupon === null) {
            return $sibling === null ? [] : [$sibling];
        }

        if ($coupon->withSibling) {
            $off = self::coupon($baseMinor - ($sibling->minor ?? 0), $coupon);

            return array_values(array_filter([$sibling, $off]));
        }

        $off = self::coupon($baseMinor, $coupon);
        if ($off !== null && $off->minor > ($sibling->minor ?? 0)) {
            return [$off];
        }

        return $sibling === null ? [] : [$sibling];
    }

    /** @param  list<Discount>  $discounts */
    public static function total(array $discounts): int
    {
        return array_sum(array_map(fn (Discount $d) => $d->minor, $discounts));
    }

    /** The lessons a group held before the course joined it, at the block's lesson price each, at most the block. */
    public static function lateJoin(int $blockMinor, int $lessons, int $sessionsPerBlock): ?Discount
    {
        if ($lessons <= 0 || $blockMinor <= 0) {
            return null;
        }
        $sessions = max(1, $sessionsPerBlock);
        $lesson = intdiv(2 * $blockMinor + $sessions, 2 * $sessions);

        return new Discount(Discount::LATE_JOIN, min($blockMinor, $lesson * $lessons), lessons: min($lessons, $sessions));
    }

    private static function coupon(int $baseMinor, CouponTerms $coupon): ?Discount
    {
        $minor = $coupon->percentBp !== null
            ? self::percent($baseMinor, $coupon->percentBp)
            : min((int) $coupon->amountMinor, $baseMinor);

        return $minor > 0 ? new Discount(Discount::COUPON, $minor, $coupon->percentBp) : null;
    }

    private static function percent(int $baseMinor, int $bp): int
    {
        return intdiv(2 * $baseMinor * $bp + 10_000, 20_000);
    }
}
