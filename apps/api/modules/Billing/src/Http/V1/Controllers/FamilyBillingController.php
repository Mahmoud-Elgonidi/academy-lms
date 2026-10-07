<?php

namespace Modules\Billing\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Filament\Family\FamilyAccount;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Billing\Payments\PayLinks;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The family's money for the app: lessons left on each of a learner's
 * courses, the invoices this person pays (with the document to open), and
 * paying one online at one of the academy's own gateway accounts.
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

        return response()->json([
            /** What this person still owes, per currency. */
            'owed' => FamilyAccount::owed($me),
            'data' => $invoices->map(function (Invoice $i) use ($me, $links, $online, $for) {
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
                        /** @var list<string> */
                        'methods' => array_map(fn (string $m) => __('billing::payments.methods.'.$m), GatewayManager::methods($a->gateway)),
                    ], $online->accountsFor($i)),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Pay an invoice online.
     *
     * Opens a checkout at the academy's gateway account (the first one when none is named) and answers its URL;
     * open it in a browser. The invoice is paid only when the gateway confirms it. Send an Idempotency-Key.
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
