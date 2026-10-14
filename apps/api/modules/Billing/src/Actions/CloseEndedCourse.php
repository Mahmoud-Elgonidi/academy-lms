<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\DTOs\EndedCourseBilling;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Subscription;

/**
 * A course ended (docs/architecture/02, section 3, "إيقاف الكورس
 * وإنهاؤه"): it never renews again (Subscription::taught), and its block
 * invoice waiting to be paid goes, when nothing may be on its way (nothing
 * paid on it, no proof waiting, no online payment under way, as for a
 * coupon's reissue): voided (a credit note for a tax invoice), and a
 * family invoice issued again with the family's other courses' blocks
 * only. Otherwise it stays, and the office sees why. A shared package's
 * block stays while another of the student's courses draws on it. The
 * course's waiting extra-lessons invoice goes on the same terms. The
 * balance left stays where it is (a refund is the academy's to make).
 */
final class CloseEndedCourse
{
    public function __construct(
        private readonly VoidInvoice $void,
        private readonly IssueBlockInvoice $issue,
    ) {}

    /** What ending this course would do to its waiting invoices, without doing it. */
    public function preview(string $enrollmentId): EndedCourseBilling
    {
        [$outcome, , $subscription] = $this->look($enrollmentId);

        return self::withExtra($outcome, $subscription === null ? null : $this->extra($enrollmentId, $subscription)?->number);
    }

    public function handle(string $enrollmentId, ?string $actorId): EndedCourseBilling
    {
        return DB::transaction(function () use ($enrollmentId, $actorId) {
            [$outcome, $waiting, $subscription] = $this->look($enrollmentId, lock: true);
            $extra = $subscription === null ? null : $this->extra($enrollmentId, $subscription, lock: true);
            if ($extra !== null) {
                $this->void->handle($extra->id, __('billing::billing.void_course_ended'), $actorId);
            }
            $outcome = self::withExtra($outcome, $extra?->number);
            if (! in_array($outcome->outcome, [EndedCourseBilling::VOIDED, EndedCourseBilling::REISSUED], true) || $waiting === null || $subscription === null) {
                return $outcome;
            }

            // The family's other courses on it are issued again on their own invoice, each block as it was.
            $others = $waiting->lines->where('kind', 'block')->filter(fn ($line) => $line->subscription_id !== null && $line->subscription_id !== $subscription->id)
                ->map(function ($line) {
                    $course = Subscription::query()->with('plan')->findOrFail($line->subscription_id);
                    [$ar, $en] = $course->subjectNames();

                    return [$course, (int) $line->meta['block_no'], $ar, $en];
                })->values()->all();
            $this->void->handle($waiting->id, __('billing::billing.void_course_ended'), $actorId);
            if ($others === []) {
                return new EndedCourseBilling(EndedCourseBilling::VOIDED, $waiting->id, $waiting->number, extraNumber: $outcome->extraNumber);
            }
            $again = $this->issue->together($others, $actorId);

            return new EndedCourseBilling(EndedCourseBilling::REISSUED, $waiting->id, $waiting->number, reissuedNumber: $again->number, extraNumber: $outcome->extraNumber);
        });
    }

    /** @return array{0: EndedCourseBilling, 1: ?Invoice, 2: ?Subscription} */
    private function look(string $enrollmentId, bool $lock = false): array
    {
        $subscription = Subscription::ofEnrollment($enrollmentId);
        if ($subscription === null) {
            return [new EndedCourseBilling(EndedCourseBilling::NONE), null, null];
        }
        $waiting = Invoice::query()->with('lines')->forSubscription($subscription->id, 'block')->where('status', 'issued')->latest('issued_at')
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        if ($waiting === null) {
            return [new EndedCourseBilling(EndedCourseBilling::NONE), null, $subscription];
        }

        $why = self::onItsWay($waiting);
        $outcome = match (true) {
            self::sharing($enrollmentId, $subscription) => new EndedCourseBilling(EndedCourseBilling::SHARED, $waiting->id, $waiting->number),
            $why !== null => new EndedCourseBilling(EndedCourseBilling::KEPT, $waiting->id, $waiting->number, $why),
            // A family invoice: the family's other courses' blocks come out again on a new one.
            $waiting->lines->contains(fn ($l) => $l->kind === 'block' && $l->subscription_id !== null && $l->subscription_id !== $subscription->id) => new EndedCourseBilling(EndedCourseBilling::REISSUED, $waiting->id, $waiting->number),
            default => new EndedCourseBilling(EndedCourseBilling::VOIDED, $waiting->id, $waiting->number),
        };

        return [$outcome, $waiting, $subscription];
    }

    /** The course's extra-lessons invoice waiting to be paid, when it may go: nothing on its way, no other course on the balance. */
    private function extra(string $enrollmentId, Subscription $subscription, bool $lock = false): ?Invoice
    {
        $extra = Invoice::query()->forSubscription($subscription->id, 'extra')->where('status', 'issued')->latest('issued_at')
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();

        return $extra === null || self::sharing($enrollmentId, $subscription) || self::onItsWay($extra) !== null ? null : $extra;
    }

    /** A shared package's invoices are the package's: they stay while another course of the student draws on it. */
    private static function sharing(string $enrollmentId, Subscription $subscription): bool
    {
        return DB::table('enrollments')->where('credit_account_id', $subscription->credit_account_id)
            ->where('id', '<>', $enrollmentId)->where('status', '<>', 'ended')->exists();
    }

    /** Why money may be on its way for this invoice (paid, proof, checkout), if it may. */
    private static function onItsWay(Invoice $invoice): ?string
    {
        return match (true) {
            $invoice->paid_minor > 0 => 'paid',
            PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists() => 'proof',
            PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->exists() => 'checkout',
            default => null,
        };
    }

    private static function withExtra(EndedCourseBilling $o, ?string $extra): EndedCourseBilling
    {
        return new EndedCourseBilling($o->outcome, $o->invoiceId, $o->invoiceNumber, $o->why, $o->reissuedNumber, $extra);
    }
}
