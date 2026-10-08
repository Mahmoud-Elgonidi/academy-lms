<?php

namespace Modules\Billing\Domain;

/** One amount off an invoice: the family discount or a coupon, with its rate when it is a percentage. */
final readonly class Discount
{
    public const SIBLING = 'sibling';

    public const COUPON = 'coupon';

    public function __construct(
        public string $source,
        public int $minor,
        public ?int $rateBp = null,
    ) {}
}
