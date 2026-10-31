<?php

namespace Modules\Scheduling\Contracts\DTOs;

/**
 * A student taking a seat in a group: the group plan, who pays and in what
 * region, a coupon, the student's level when known, and for a group that
 * already began, what makes up for the lessons missed (the office's choice:
 * discount, private, group or none; null: the academy's setting) and how
 * many (null: all the missed ones; never more).
 */
final readonly class GroupJoin
{
    public function __construct(
        public string $planId,
        public string $payerId,
        public string $priceRegionId,
        public ?string $couponCode = null,
        public ?string $levelId = null,
        public ?string $lateJoin = null,
        public ?int $lateJoinLessons = null,
    ) {}
}
