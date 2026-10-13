<?php

namespace Modules\Billing\Filament\Family;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Plan;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\CourseBillingQuery;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Billing\Queries\TransferDetails;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Money\Money;

/** The family's money in plain words: lessons left on each course, and the invoices this person pays. */
final class FamilyAccount
{
    /**
     * Lessons left on each balance: each course's own, or once for a shared
     * package with all its subjects named (shared: true).
     *
     * @return list<array{enrollment_id: string, subject: ?string, shared: bool, sessions: ?int, minutes: int, session_minutes: ?int, extra: ?array<string, mixed>}>
     */
    public static function balances(string $learnerId, ?string $payerId = null): array
    {
        $enrollments = array_values(array_filter(app(EnrollmentApi::class)->forStudent($learnerId),
            fn (EnrollmentData $e) => in_array($e->status->value, ['active', 'pending_payment'], true) && $e->creditAccountId !== null));
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');
        $plans = DB::table('subscriptions as s')->join('plans as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.id', '=', 's.plan_id'))
            ->whereIn('s.credit_account_id', array_map(fn (EnrollmentData $e) => $e->creditAccountId, $enrollments))->where('s.status', '<>', 'cancelled')
            ->get(['s.credit_account_id', 'p.session_minutes', 'p.credit_scope'])->keyBy('credit_account_id');

        $rows = [];
        foreach (collect($enrollments)->groupBy(fn (EnrollmentData $e) => $e->creditAccountId) as $accountId => $courses) {
            /** @var EnrollmentData $first */
            $first = $courses->first();
            $minutes = app(CreditsApi::class)->balance((string) $accountId)->remainingMin();
            $plan = $plans[$accountId] ?? null;
            $per = $plan ? (int) $plan->session_minutes : null;
            $active = $courses->contains(fn (EnrollmentData $e) => $e->status->value === 'active');

            $rows[] = [
                'enrollment_id' => $first->id,
                'subject' => $courses->map(fn (EnrollmentData $e) => $subjects[$e->subjectId] ?? null)->filter()->unique()->implode(app()->getLocale() === 'ar' ? '، ' : ', ') ?: null,
                'shared' => ($plan->credit_scope ?? null) === Plan::SHARED,
                'minutes' => $minutes,
                'session_minutes' => $per,
                'sessions' => $per ? intdiv($minutes, $per) : null,
                'extra' => $payerId === null ? null : self::extraOffer($first->id, $active, $payerId),
            ];
        }

        return $rows;
    }

    /**
     * Extra lessons the payer can buy on this course: a lesson's price and
     * length, and the invoice for them waiting to be paid, if one. Only for
     * whoever pays for the course, while it is on.
     *
     * @return array{price: string, price_minor: int, currency: string, minutes: int, max: int, waiting: ?array{id: string, number: string}}|null
     */
    public static function extraOffer(string $enrollmentId, bool $active, string $payerId): ?array
    {
        $course = app(CourseBillingQuery::class)->forEnrollment($enrollmentId, $active);
        if ($course === null || ! $course->active || $course->payerId !== $payerId) {
            return null;
        }

        return [
            'price' => $course->lessonPrice->format(),
            'price_minor' => $course->lessonPrice->minor,
            'currency' => $course->lessonPrice->currency,
            'minutes' => $course->sessionMinutes,
            'max' => BuyExtraLessons::MAX,
            'waiting' => $course->waitingExtraInvoiceId === null ? null : ['id' => $course->waitingExtraInvoiceId, 'number' => (string) $course->waitingExtraNumber],
        ];
    }

    /** @return list<array<string, mixed>> the invoices this person pays, the latest first */
    public static function invoices(string $payerId, string $tz, int $limit = 24): array
    {
        $rows = Invoice::query()
            ->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'invoices.academy_id')->on('ba.id', '=', 'invoices.billing_account_id'))
            ->with('lines')
            ->where('ba.payer_id', $payerId)->whereIn('invoices.status', ['issued', 'paid'])
            ->orderByDesc('invoices.issued_at')->limit($limit)
            ->get(['invoices.*']);

        $proofs = PaymentProof::latestFor($rows->pluck('id')->all());
        $courses = InvoiceCourses::of($rows->pluck('id')->all());
        // Where to send the money by hand, for each invoice still to pay (its payer's region's words, else the academy's).
        $transfers = app(TransferDetails::class)->forInvoices($rows);

        return $rows->map(fn (Invoice $i) => [
            'id' => $i->id,
            'number' => $i->number,
            'for' => InvoiceCourses::label($courses[$i->id] ?? []),
            'family' => count($courses[$i->id] ?? []) > 1,
            'students' => array_values(array_unique(array_column($courses[$i->id] ?? [], 'student_id'))),
            'total' => $i->total()->format(),
            'outstanding' => $i->status === 'issued' ? $i->outstanding()->format() : null,
            'payable' => $i->status === 'issued' && $i->outstanding()->minor > 0,
            'currency' => $i->currency,
            'status' => InvoiceResource::statusKey($i),
            'statusLabel' => __('billing::billing.statuses.'.InvoiceResource::statusKey($i)),
            'issued' => $i->issued_at ? CarbonImmutable::parse($i->issued_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y') : null,
            'due' => $i->due_at ? CarbonImmutable::parse($i->due_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y') : null,
            'paid' => $i->paid_at ? CarbonImmutable::parse($i->paid_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y') : null,
            'document' => app(PayLinks::class)->document($i, $payerId),
            'discounts' => $i->lines->where('kind', 'discount')->map(fn ($l) => ['label' => $l->description(), 'amount' => Money::of($l->amount_minor, $i->currency)->format()])->values()->all(),
            'proof' => isset($proofs[$i->id]) ? self::proofLine($proofs[$i->id], $tz) : null,
            'transfer' => $transfers[$i->id] ?? null,
            // Paying by hand takes the academy's transfer details for this payer, something left to pay, and no proof waiting.
            'canSendProof' => ($transfers[$i->id] ?? null) !== null && ($proofs[$i->id]->status ?? null) !== 'pending',
        ])->all();
    }

    /** @return array{status: string, text: string} the latest proof on an invoice, in a sentence */
    public static function proofLine(PaymentProof $proof, string $tz): array
    {
        return ['status' => $proof->status, 'text' => __('billing::proofs.state.'.$proof->status, [
            'amount' => $proof->money()->format(),
            'date' => $proof->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F'),
            'reason' => $proof->decision_note,
        ])];
    }

    /**
     * Whether money is shown to the person signed in: guardians and adults
     * (the classic look), and a student only when they pay for themselves.
     */
    public static function shown(): bool
    {
        $viewer = (string) auth()->id();

        return app(FamilyView::class)->stage($viewer) === LearnerStage::Classic
            || DB::table('billing_accounts')->where('payer_id', $viewer)->exists();
    }

    /** What this person still owes, per currency, formatted. */
    public static function owed(string $payerId): ?string
    {
        $owed = Invoice::query()->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'invoices.academy_id')->on('ba.id', '=', 'invoices.billing_account_id'))
            ->where('ba.payer_id', $payerId)->where('invoices.status', 'issued')
            ->selectRaw('invoices.currency, sum(invoices.total_minor - invoices.paid_minor) as owed')->groupBy('invoices.currency')->get()
            ->filter(fn ($r) => (int) $r->owed > 0)
            ->map(fn ($r) => Money::of((int) $r->owed, $r->currency)->format());

        return $owed->isEmpty() ? null : $owed->implode(' + ');
    }
}
