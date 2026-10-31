<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Modules\Billing\Contracts\DTOs\IssuedInvoice;

/** A seat taken: the student's own course in the group, its first invoice, and what made up for the lessons missed. */
final readonly class GroupJoined
{
    public function __construct(
        public string $enrollmentId,
        public string $memberId,
        public IssuedInvoice $invoice,
        public int $seat,                 // the seat they took (3 of 5: 3)
        public int $capacity,
        public int $lessons,              // group lessons they are seated in so far
        public int $missed,               // lessons of the current block the group held before they joined
        public string $lateJoin,          // discount | private | group | none
        public int $lateJoinLessons,      // how many the choice covers
        public bool $started,             // the group started with them
    ) {}
}
