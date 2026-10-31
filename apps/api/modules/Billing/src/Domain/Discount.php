<?php

namespace Modules\Billing\Domain;

/** One amount off an invoice: the family discount, a coupon (with its rate when it is a percentage), or a group's lessons held before joining it. */
final readonly class Discount
{
    public const SIBLING = 'sibling';

    public const COUPON = 'coupon';

    public const LATE_JOIN = 'late_join';

    public function __construct(
        public string $source,
        public int $minor,
        public ?int $rateBp = null,
        public ?int $lessons = null,          // a late join: how many lessons it is for
    ) {}
}
