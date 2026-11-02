<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A course the family started: its enrollment, the first invoice (none when it
 * joined a shared package), the teacher, its weekly times (each as its first
 * lesson's instant), the first lesson, and what became of the trial's offer:
 * applied, ended (its time passed: the plain price), family_discount_bigger,
 * not_for_plan, package (a shared package has no invoice of its own) or none;
 * and the pay-by moment, which is also the first invoice's due date. The
 * skipped weeks: the first weeks it starts without (an academy holiday or
 * the teacher's leave; at in UTC, date on the teacher's clock), never booked.
 */
final readonly class OwnStarted
{
    /**
     * @param  list<CarbonImmutable>  $weeklyTimes
     * @param  list<string>  $skippedDates  days with no lesson (the teacher's clock): skipped weeks, and dates already booked
     * @param  list<array{at: int, date: string, reason: string}>  $skippedWeeks
     */
    public function __construct(
        public string $enrollmentId,
        public ?string $invoiceId,
        public string $teacherId,
        public array $weeklyTimes,
        public ?CarbonImmutable $firstLesson,
        public int $lessons,
        public array $skippedDates,
        public bool $joinedPackage,
        public string $offer,
        public ?int $offerPercentBp,
        public ?string $groupId = null,
        public ?int $seat = null,
        public ?int $capacity = null,
        public bool $groupStarted = false,
        public ?CarbonImmutable $payBy = null,   // pay before this to keep the lessons from the first payable one (the invoice's due date)
        public array $skippedWeeks = [],
    ) {}
}
