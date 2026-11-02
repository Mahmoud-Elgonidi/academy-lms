<?php

namespace Modules\Billing\Contracts;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\DTOs\CourseBilling;
use Modules\Billing\Contracts\DTOs\EndedCourseBilling;
use Modules\Billing\Contracts\DTOs\InvoiceSummary;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;
use Modules\Billing\Contracts\DTOs\NewPlan;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PersonalCoupon;
use Modules\Billing\Contracts\DTOs\PersonalCouponState;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Contracts\DTOs\SharedPackage;
use Modules\SharedKernel\Money\Money;

/** What other modules may ask of Billing. */
interface BillingApi
{
    /**
     * Puts an enrollment on a plan and issues the invoice for its first
     * block. Paying it grants the block's minutes to the credit account.
     */
    public function subscribe(NewSubscription $new): IssuedInvoice;

    /**
     * Puts an enrollment brought over from before the platform on a plan:
     * the block under way was paid there (its minutes left are the opening
     * balance), so the first invoice is the next block's, when the balance
     * reaches the renewal mark. Returns the subscription id.
     */
    public function adopt(NewSubscription $new): string;

    /**
     * Records money received for an invoice (a transfer, cash, a wallet). Paid
     * in full, it grants the block's minutes in the same transaction.
     */
    public function recordPayment(string $invoiceId, string $method, Money $amount, ?string $reference, CarbonImmutable $receivedAt, ?string $actorId): void;

    /** @return list<PlanOption> active plans, those with this session length first; with a format (private, group), only those */
    public function planOptions(?int $sessionMinutes = null, ?string $lessonFormat = null): array;

    /** @return list<RegionOption> */
    public function regions(): array;

    /**
     * The price region a family's prices are in, the one rule for every
     * place that prices for a family (the trial's report, the start flow,
     * its invoice): the region the payer already pays in (their billing
     * account), else the one naming the payer's country, else the learner's
     * (People's: a student's details, a guardian's profile), else the
     * default one. Null when the academy has no region.
     */
    public function priceRegionFor(?string $payerId, ?string $learnerId): ?RegionOption;

    /**
     * Makes a personal coupon (a trial offer) through SaveCoupon: a
     * percentage off the first invoice of one course, for this payer alone,
     * once, until its moment, never on top of the family discount (the bigger
     * applies). Its code is random and never needs typing.
     */
    public function issuePersonalCoupon(PersonalCoupon $coupon): PersonalCouponState;

    /**
     * Sets when an issued invoice with nothing paid on it is due (SetInvoiceDue):
     * a course a family started on its own is due by its pay-by moment, not
     * the academy's general number of days. Returns whether it changed.
     */
    public function setInvoiceDue(string $invoiceId, CarbonImmutable $dueAt): bool;

    /** A personal coupon as it stands now; null when there is none (another academy's, or a staff code). */
    public function personalCoupon(string $couponId): ?PersonalCouponState;

    /** Makes an active plan through SavePlan (its checks and audit) and answers its id. */
    public function createPlan(NewPlan $plan): string;

    /**
     * The student's live shared package on this plan, paid by this payer,
     * for a new course to join (its lessons then draw on the package's
     * balance, and it has no invoice of its own). Null when there is none.
     */
    public function sharedPackage(string $studentId, string $planId, string $payerId): ?SharedPackage;

    /**
     * What a first block would come to for this student and payer: the
     * plan's regional price, the family discount, the coupon (or why it
     * cannot be used), and a group's lessons the course joins too late for
     * (the lesson's price each, at most the block). Null when the plan has no
     * price in the region.
     */
    public function quote(string $planId, string $priceRegionId, string $payerId, string $studentId, ?string $couponCode = null, int $lateJoinLessons = 0): ?PriceQuote;

    /** A course's money side: extra lessons' price, its coupon, invoices waiting. Null without a subscription. */
    public function courseBilling(string $enrollmentId): ?CourseBilling;

    /**
     * Puts a coupon on a course; its next block invoices come out with it.
     * With $reissue, the block invoice waiting to be paid is voided and
     * issued again with it. Returns the new invoice's number, if one.
     */
    public function applyCoupon(string $enrollmentId, string $code, bool $reissue, ?string $actorId): ?string;

    public function removeCoupon(string $enrollmentId, ?string $actorId): void;

    /** Issues an invoice for N extra lessons on a course; paying it adds their minutes. */
    public function sellExtraLessons(string $enrollmentId, int $lessons, ?string $actorId): IssuedInvoice;

    /**
     * What ending this course would do to its block invoice waiting to be paid (nothing waiting, voided, issued again
     * for the family's other courses, kept and why, or a shared package's that stays), without doing it.
     */
    public function endingCourse(string $enrollmentId): EndedCourseBilling;

    /**
     * The course has ended (it never renews again): its block invoice waiting to be paid is voided when nothing may
     * be on its way (nothing paid, no proof waiting, no online payment under way); a family invoice comes out again
     * with the other courses' blocks. A shared package's stays for its other courses. The balance left stays.
     */
    public function closeEndedCourse(string $enrollmentId, ?string $actorId): EndedCourseBilling;

    /** Whether families may start a course and pay on their own (billing_settings.self_serve_enrol; J3). */
    public function selfServeEnrol(): bool;

    /** An invoice as its payer reads it before paying (lines, discounts, VAT, its signed pages); null when there is none. */
    public function invoiceSummary(string $invoiceId, ?string $payerId): ?InvoiceSummary;

    /** The course's first block invoice (the one its start issued), as its payer reads it; null without one (a shared package it joined). */
    public function firstInvoice(string $enrollmentId, ?string $payerId): ?InvoiceSummary;

    /**
     * Whether money may be on its way for this invoice: paid_some (something paid on it), proof_waiting (a transfer
     * receipt the academy has not decided) or checkout_open (an online payment page still open); null when none.
     */
    public function paymentUnderway(string $invoiceId): ?string;

    /**
     * Sends the payment link of this course's invoice waiting to be paid (its block first) to the payer's channels
     * (SendPayLink: audited, at most once an hour per invoice; refused in the office's words when paid, a transfer
     * receipt waits, or nothing waits). Returns the invoice's number.
     */
    public function sendPayLink(string $enrollmentId, string $actorId): string;
}
