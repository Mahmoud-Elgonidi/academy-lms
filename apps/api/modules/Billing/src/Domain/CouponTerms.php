<?php

namespace Modules\Billing\Domain;

use InvalidArgumentException;

/** What a coupon takes off: a percentage, or an amount in the invoice's currency; and whether it adds to the family discount. */
final readonly class CouponTerms
{
    public function __construct(
        public ?int $percentBp,
        public ?int $amountMinor,
        public bool $withSibling = false,
    ) {
        if (($percentBp === null) === ($amountMinor === null) || ($percentBp !== null && ($percentBp < 1 || $percentBp > 10_000)) || ($amountMinor !== null && $amountMinor <= 0)) {
            throw new InvalidArgumentException('A coupon takes off a percentage or an amount.');
        }
    }

    public static function percent(int $bp, bool $withSibling = false): self
    {
        return new self($bp, null, $withSibling);
    }

    public static function amount(int $minor, bool $withSibling = false): self
    {
        return new self(null, $minor, $withSibling);
    }
}
