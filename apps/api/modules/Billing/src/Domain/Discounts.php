<?php

namespace Modules\Billing\Domain;

use InvalidArgumentException;

/**
 * What comes off an invoice (docs/architecture/02, section 3, "الخصومات"):
 * the family discount for a second or later child, and a coupon. A coupon
 * either adds to the family discount (taken from what is left after it) or,
 * by default, the bigger of the two applies; on a tie the family discount
 * does, so a coupon is not used up for nothing. Rounded half up per line,
 * and never more than the price.
 */
final class Discounts
{
    /** @return list<Discount> in the order they are taken off */
    public static function on(int $baseMinor, int $siblingBp, ?CouponTerms $coupon): array
    {
        if ($baseMinor < 0 || $siblingBp < 0 || $siblingBp > 10_000) {
            throw new InvalidArgumentException('A price and a rate are not negative, and a rate is at most 100%.');
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
