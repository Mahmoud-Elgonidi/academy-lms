<?php

namespace Modules\Billing\Http\V1\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
     * Per active course: the minutes left, and as lessons when the plan has a lesson length.
     */
    public function balance(Request $request, FamilyView $family): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $learner = $family->learner((string) $request->user()?->getAuthIdentifier(), $request->query('learner_id')) ?? throw new NotFoundHttpException;

        return response()->json([
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            'data' => FamilyAccount::balances($learner->id),
        ]);
    }

    /**
     * My invoices.
     *
     * The invoices this person pays, the latest first; document_url opens the invoice (with its tax QR) in a browser.
     * A child who does not pay for themselves sees no money.
     *
     * @throws AccessDeniedHttpException a child who does not pay
     */
    public function invoices(Request $request, PayLinks $links, OnlinePayments $online): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        if (! FamilyAccount::shown()) {
            throw new AccessDeniedHttpException;
        }
        $rows = collect(FamilyAccount::invoices($me, 'UTC', 50));
        $invoices = Invoice::query()->whereIn('id', $rows->pluck('id'))->orderByDesc('issued_at')->get();
        $for = $rows->pluck('for', 'id');
        $proofs = PaymentProof::latestFor($invoices->pluck('id')->all());
        $takesProofs = $online->takesProofs();

        return response()->json([
            /** What this person still owes, per currency. */
            'owed' => FamilyAccount::owed($me),
            /** Paying by hand: how (the academy's words: an account number, InstaPay…), and whether a proof can be sent (POST /family/invoices/{id}/proofs). */
            'manual' => [
                'note' => $online->manualNote(),
                'takes_proofs' => $takesProofs,
                /** The ways a proof can say it was paid. */
                'methods' => array_map(fn (string $m) => ['value' => $m, 'label' => __('billing::billing.methods.'.$m)], PaymentProof::METHODS),
            ],
            'data' => $invoices->map(function (Invoice $i) use ($me, $links, $online, $for, $proofs, $takesProofs) {
                $outstanding = $i->status === 'issued' ? $i->outstanding() : Money::of(0, $i->currency);
                $status = InvoiceResource::statusKey($i);

                return [
                    'id' => $i->id,
                    'number' => $i->number,
                    /** The student and the course. */
                    'for' => $for[$i->id] ?? null,
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
                    /** Where it can be paid now: POST /family/invoices/{id}/checkout with one of these. */
                    'pay_with' => array_map(fn (GatewayAccount $a) => [
                        'account_id' => $a->id,
                        'label' => $a->label,
                        'methods' => array_map(fn (string $m) => __('billing::payments.methods.'.$m), GatewayManager::methods($a->gateway)),
                    ], $online->accountsFor($i)),
                    /** The latest proof of paying by hand on it, if any: pending, approved or rejected (with the academy's reason). */
                    'proof' => isset($proofs[$i->id]) ? $this->proof($proofs[$i->id]) : null,
                    /** Whether a proof can be sent now (the academy takes them, something is owed, none is waiting). */
                    'can_send_proof' => $takesProofs && $outstanding->minor > 0 && ($proofs[$i->id]->status ?? null) !== 'pending',
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
