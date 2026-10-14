<?php

namespace Modules\Billing\Http\V1\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Filament\Family\FamilyAccount;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\CourseBillingQuery;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Billing\Queries\TransferDetails;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The family's money for the app: lessons left on each of a learner's
 * courses, the invoices this person pays (with the document to open), and
 * paying one online at one of the academy's own gateway accounts, or by
 * hand with a proof the academy confirms.
 */
final class FamilyBillingController
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * A learner's balance.
     *
     * Per course still on (running or paused): the minutes left, and as lessons when the plan has a lesson length.
     * A paused course says until when (paused_until, a day on the academy's calendar); an ended one is listed while
     * minutes are left on it.
     */
    public function balance(Request $request, FamilyView $family): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $learner = $family->learner((string) $request->user()?->getAuthIdentifier(), $request->query('learner_id')) ?? throw new NotFoundHttpException;

        $courses = [];
        foreach (FamilyAccount::balances($learner->id, (string) $request->user()?->getAuthIdentifier()) as $b) {
            $extra = $b['extra'];
            $courses[] = [
                'enrollment_id' => (string) $b['enrollment_id'],
                /** The course's subject, or a shared package's subjects together. */
                'subject' => $b['subject'] === null ? null : (string) $b['subject'],
                /** A shared package ("12 lessons in any subject"): one balance for all these courses. */
                'shared' => (bool) $b['shared'],
                'minutes' => (int) $b['minutes'],
                /** A lesson's length, when the plan has one. */
                'session_minutes' => $b['session_minutes'] === null ? null : (int) $b['session_minutes'],
                /** The minutes left as whole lessons. */
                'sessions' => $b['sessions'] === null ? null : (int) $b['sessions'],
                /** Extra lessons this person can buy on the course (they pay for it, it is on): POST /family/enrollments/{id}/extra-lessons. */
                'extra' => $extra === null ? null : [
                    'price' => (string) $extra['price'],
                    'price_minor' => (int) $extra['price_minor'],
                    'currency' => (string) $extra['currency'],
                    'minutes' => (int) $extra['minutes'],
                    'max' => (int) $extra['max'],
                    /** The invoice for extra lessons waiting to be paid; another cannot be made until it is. */
                    'waiting_invoice' => $extra['waiting'] === null ? null : ['id' => (string) $extra['waiting']['id'], 'number' => (string) $extra['waiting']['number']],
                ],
                /** pending_payment, active, paused or ended (a course over, listed while minutes are left on it). */
                'status' => (string) $b['status'],
                /** The pause's first day (it may lie ahead while the course is still active), on the academy's calendar. */
                'paused_from' => $this->time->academyDay($b['paused_from']),
                /** The pause's last day (included): lessons start again the day after. */
                'paused_until' => $this->time->academyDay($b['paused_until']),
                /** The first day without lessons of a course ending (or ended). */
                'ends_on' => $this->time->academyDay($b['ends_on']),
            ];
        }

        return response()->json([
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            'data' => $courses,
        ]);
    }

    /**
     * Buy extra lessons.
     *
     * An invoice for N extra lessons on a course of the learner, at a lesson's price (less the family discount),
     * for whoever pays for the course. Paying it adds the lessons' minutes to the course; the academy books them.
     * One such invoice waits at a time per course. Send an Idempotency-Key.
     *
     * @throws NotFoundHttpException not a course of my learner that I pay for
     */
    public function extraLessons(Request $request, string $enrollment, FamilyView $family, BuyExtraLessons $buy, CourseBillingQuery $courses): JsonResponse
    {
        $data = $request->validate([
            'lessons' => ['required', 'integer', 'min:1', 'max:'.BuyExtraLessons::MAX],
            'learner_id' => ['nullable', 'string', 'max:64'],
        ]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $learner = $family->learner($me, $data['learner_id'] ?? null) ?? throw new NotFoundHttpException;
        $course = collect(FamilyAccount::balances($learner->id, $me))->firstWhere('enrollment_id', $enrollment);
        if ($course === null || $course['extra'] === null) {
            throw new NotFoundHttpException;
        }

        $invoice = $buy->handle((string) $courses->subscriptionOf($enrollment)?->id, (int) $data['lessons'], $me, 'family');

        return response()->json(['data' => [
            'id' => $invoice->id,
            'number' => (string) $invoice->number,
            'currency' => $invoice->currency,
            'total_minor' => $invoice->total_minor,
            'total' => $invoice->total()->format(),
            /** paid at once when it came to nothing */
            'status' => $invoice->status,
        ]], 201);
    }

    /**
     * My invoices.
     *
     * The invoices this person pays, the latest first; document_url opens the invoice (with its tax QR) in a browser.
     * Each one still to pay says where to send the money by hand (transfer_details, the academy's words for the payer's region).
     * A child who does not pay for themselves sees no money.
     *
     * @throws AccessDeniedHttpException a child who does not pay
     */
    public function invoices(Request $request, PayLinks $links, OnlinePayments $online, TransferDetails $transfer): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        if (! FamilyAccount::shown()) {
            throw new AccessDeniedHttpException;
        }
        $rows = collect(FamilyAccount::invoices($me, 'UTC', 50));
        $invoices = Invoice::query()->with('lines')->whereIn('id', $rows->pluck('id'))->orderByDesc('issued_at')->get();
        $for = $rows->pluck('for', 'id');
        $family = $rows->keyBy('id');
        $courses = InvoiceCourses::of($invoices->pluck('id')->all());
        $proofs = PaymentProof::latestFor($invoices->pluck('id')->all());
        $note = $transfer->forPayer($me);
        $transfers = $rows->pluck('transfer', 'id')->all();

        return response()->json([
            /** What this person still owes, per currency. */
            'owed' => FamilyAccount::owed($me),
            /** Paying by hand: how, and whether a proof can be sent (POST /family/invoices/{id}/proofs). */
            'manual' => [
                /** For earlier app versions: the one text this person's invoices go by (null when they differ). Show each invoice's transfer_details instead. */
                'note' => $note,
                /** Whether this person can send proofs at all: the academy wrote its transfer details for them. */
                'takes_proofs' => (bool) ($note !== null || array_filter($transfers) !== []),
                /** The ways a proof can say it was paid. */
                'methods' => array_map(fn (string $m) => ['value' => $m, 'label' => __('billing::billing.methods.'.$m)], PaymentProof::METHODS),
            ],
            'data' => $invoices->map(function (Invoice $i) use ($me, $links, $online, $for, $family, $courses, $proofs, $transfers) {
                $outstanding = $i->status === 'issued' ? $i->outstanding() : Money::of(0, $i->currency);
                $status = InvoiceResource::statusKey($i);
                $studentIds = [];
                foreach ($family[$i->id]['students'] ?? [] as $studentId) {
                    $studentIds[] = (string) $studentId;
                }
                // Each course's lines together, as the invoice document shows them.
                $order = array_flip(array_column($courses[$i->id] ?? [], 'subscription_id'));
                $lines = [];
                foreach ($i->lines->sortBy(fn ($l) => [$order[$l->subscription_id] ?? PHP_INT_MAX, $l->kind === 'discount' ? 1 : 0]) as $line) {
                    $lines[] = [
                        /** block, extra (extra lessons) or discount (the family discount or a coupon: a negative amount) */
                        'kind' => $line->kind,
                        'description' => $line->description(),
                        'quantity' => $line->quantity,
                        'amount_minor' => $line->amount_minor,
                        'amount' => Money::of($line->amount_minor, $i->currency)->format(),
                    ];
                }

                return [
                    'id' => $i->id,
                    'number' => $i->number,
                    /** The student and the course; on a family invoice, each child's. */
                    'for' => $for[$i->id] ?? null,
                    /** One invoice for several children's renewals that fell due in the same week. */
                    'family' => (bool) ($family[$i->id]['family'] ?? false),
                    /** The learners it is for (ids, as learner_id elsewhere). */
                    'student_ids' => $studentIds,
                    'currency' => $i->currency,
                    'total_minor' => $i->total_minor,
                    'total' => $i->total()->format(),
                    'outstanding_minor' => $outstanding->minor,
                    'outstanding' => $outstanding->minor > 0 ? $outstanding->format() : null,
                    /** What is left as a number to type in a form ("400.00"), in the currency's own decimals. */
                    'outstanding_decimal' => $outstanding->decimal(),
                    /** issued, partly_paid, overdue, paid */
                    'status' => $status,
                    'status_label' => __('billing::billing.statuses.'.$status),
                    'issued_at' => $this->time->present($i->issued_at),
                    'due_at' => $this->time->present($i->due_at),
                    'paid_at' => $this->time->present($i->paid_at),
                    'document_url' => $links->document($i, $me),
                    /** What it is for and what came off it, as on the invoice. */
                    'lines' => $lines,
                    /** Where it can be paid now: POST /family/invoices/{id}/checkout with one of these. */
                    'pay_with' => array_map(fn (GatewayAccount $a) => [
                        'account_id' => $a->id,
                        'label' => $a->label,
                        'methods' => array_map(fn (string $m) => __('billing::payments.methods.'.$m), GatewayManager::methods($a->gateway)),
                    ], $online->accountsFor($i)),
                    /** The latest proof of paying by hand on it, if any: pending, approved or rejected (with the academy's reason). */
                    'proof' => isset($proofs[$i->id]) ? $this->proof($proofs[$i->id]) : null,
                    /**
                     * Where to send the money by hand for it, in the academy's words for this payer's price region (else the academy's own):
                     * plain text of several lines (keep the line breaks, never read it as HTML). Null once nothing is left to pay, or when the academy wrote none.
                     */
                    'transfer_details' => ($transfers[$i->id] ?? null) === null ? null : (string) $transfers[$i->id],
                    /** Whether a proof can be sent now (the academy wrote where to transfer, something is owed, none is waiting). */
                    'can_send_proof' => $transfers[$i->id] !== null && $outstanding->minor > 0 && ($proofs[$i->id]->status ?? null) !== 'pending',
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Send proof of paying an invoice by hand.
     *
     * multipart/form-data with the receipt (a photo, a screenshot or a PDF, up to 10 MB). paid_on is the payer's own day (Y-m-d).
     * Nothing is paid yet: the academy checks the money arrived, then approves it (or turns it down with a reason).
     * One proof waits per invoice. Send an Idempotency-Key.
     *
     * @throws NotFoundHttpException not an invoice I pay
     */
    public function sendProof(Request $request, string $invoice, SubmitPaymentProof $submit): JsonResponse
    {
        $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', PaymentProof::METHODS)],
            'amount' => ['required', 'numeric'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:'.PaymentProof::MAX_KB],
        ]);
        $own = Str::isUuid($invoice) ? Invoice::query()->find($invoice) : null;
        if ($own === null) {
            throw new NotFoundHttpException;
        }
        try {
            $proof = $submit->handle($own, (string) $request->user()?->getAuthIdentifier(), 'family', $request->input('method'), $request->input('amount'),
                $request->input('paid_on'), $request->input('reference'), $request->input('note'), $request->file('file'));
        } catch (AuthorizationException) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => $this->proof($proof)], 201);
    }

    /** @return array<string, mixed> */
    private function proof(PaymentProof $p): array
    {
        return [
            'id' => $p->id,
            /** pending, approved, rejected */
            'status' => $p->status,
            'status_label' => __('billing::proofs.statuses.'.$p->status),
            'method' => $p->method,
            'amount' => $p->money()->format(),
            /** The day the payer said they paid, as they wrote it (Y-m-d). */
            'paid_on' => $p->paid_on->toDateString(),
            'sent_at' => $this->time->present($p->created_at),
            /** Where it stands, in a sentence for the payer. */
            'text' => __('billing::proofs.state.'.$p->status, [
                'amount' => $p->money()->format(),
                'date' => $p->created_at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j F'),
                'reason' => $p->decision_note,
            ]),
            /** Why the academy turned it down. */
            'reason' => $p->status === 'rejected' ? $p->decision_note : null,
        ];
    }

    /**
     * Pay an invoice online.
     *
     * Opens a checkout at the academy's gateway account (the first one when none is named) and answers its URL;
     * open it in a browser. The invoice is paid only when the gateway confirms it. Send an Idempotency-Key.
     *
     * @throws NotFoundHttpException not an invoice I pay
     */
    public function checkout(Request $request, string $invoice, OnlinePayments $online, StartCheckout $start): JsonResponse
    {
        $request->validate(['account_id' => ['nullable', 'string', 'max:64']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $own = Str::isUuid($invoice) ? Invoice::query()->find($invoice) : null;
        if ($own === null || DB::table('billing_accounts')->where('id', $own->billing_account_id)->value('payer_id') !== $me) {
            throw new NotFoundHttpException;
        }

        $accounts = collect($online->accountsFor($own));
        $account = $request->filled('account_id') ? $accounts->firstWhere('id', $request->input('account_id')) : $accounts->first();
        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => __('billing::payments.family.unavailable')]);
        }
        $checkout = $start->handle($own, $account, $me, 'link');

        return response()->json([
            'checkout_id' => $checkout->id,
            'url' => $checkout->url,
            'expires_at' => $this->time->present($checkout->expires_at),
        ], 201);
    }
}
