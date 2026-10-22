<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Billing\Queries\InvoiceDocument;
use Modules\Billing\Queries\TransferDetails;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerClock;
use Throwable;

/**
 * An invoice and its ways to pay, for the payer, in the academy's look and
 * without signing in (the signed link in the invoice message): the online
 * gateways that take its currency, or where to transfer the money by hand
 * (the academy's transfer details for the payer's region) and sending the
 * receipt for the academy to confirm. Back from a
 * gateway, the checkout is settled by asking the gateway, then the payer
 * sees where it stands.
 */
final class PayController
{
    public function show(Request $request, Invoice $invoice, OnlinePayments $online, TransferDetails $transfer, BrandingApi $branding, TenantContext $tenants): View
    {
        $payerId = $request->query('u');
        $this->speak($payerId, $tenants);
        $checkout = $request->query('checkout') ? PaymentCheckout::query()->where('invoice_id', $invoice->id)->find($request->query('checkout')) : null;
        $student = InvoiceCourses::students(InvoiceCourses::ofOne($invoice->id)) ?: null;
        // Where to send the money by hand (the payer's region's words, else the academy's), while something is left to pay.
        $manual = $transfer->forInvoice($invoice);

        return view('billing::pay.show', [
            'invoice' => $invoice,
            'student' => $student,
            'branding' => $branding->current(),
            'checkout' => $checkout,
            'options' => $invoice->status === 'issued' ? array_map(fn (GatewayAccount $account) => [
                'account' => $account,
                'url' => URL::temporarySignedRoute('billing.pay.start', now()->addHours(2), array_filter(['invoice' => $invoice->id, 'account' => $account->id, 'u' => $payerId]), absolute: false),
            ], $online->accountsFor($invoice)) : [],
            'manual' => $manual,
            'tz' => $tenants->require()->defaultTimezone,
            'document' => app(PayLinks::class)->document($invoice, $payerId),
            'proof' => PaymentProof::latestFor([$invoice->id])[$invoice->id] ?? null,
            'proofUrl' => $manual !== null
                ? URL::temporarySignedRoute('billing.pay.proof', now()->addHours(6), array_filter(['invoice' => $invoice->id, 'u' => $payerId]), absolute: false)
                : null,
            'today' => CarbonImmutable::now($tenants->require()->defaultTimezone)->toDateString(),
        ]);
    }

    /** The payer sends proof of paying by hand from their link; it waits for the academy. */
    public function proof(Request $request, Invoice $invoice, SubmitPaymentProof $submit, PayLinks $links, TenantContext $tenants): RedirectResponse
    {
        $payerId = $request->query('u') ?: DB::table('billing_accounts')->where('id', $invoice->billing_account_id)->value('payer_id');
        $this->speak($payerId, $tenants);
        $back = $links->invoice($invoice, $request->query('u'));
        try {
            $submit->handle($invoice, (string) $payerId, 'link', $request->input('method'), $request->input('amount'), $request->input('paid_on'),
                $request->input('reference'), $request->input('note'), $request->file('file'));
        } catch (AuthorizationException) {
            abort(404);
        } catch (ValidationException $e) {
            return redirect()->to($back)->withErrors($e->errors())->withInput($request->except('file'));
        }

        return redirect()->to($back)->with('proof_sent', true);
    }

    /** The invoice as a document to read and print (a Saudi tax invoice with its QR code, and the credit note that cancelled it). */
    public function document(Request $request, Invoice $invoice, InvoiceDocument $documents, BrandingApi $branding, TenantContext $tenants, PayLinks $links): View
    {
        $payerId = $request->query('u');
        $this->speak($payerId, $tenants);

        return view('billing::pay.document', [
            'd' => $documents->build($invoice, $tenants->require()->defaultTimezone),
            'branding' => $branding->current(),
            'back' => $invoice->status === 'issued' ? $links->invoice($invoice, $payerId) : null,
        ]);
    }

    public function start(Request $request, Invoice $invoice, GatewayAccount $account, StartCheckout $start, PayLinks $links): RedirectResponse
    {
        try {
            $checkout = $start->handle($invoice, $account, $request->query('u'), 'link');
        } catch (ValidationException $e) {
            return redirect()->to($links->invoice($invoice, $request->query('u')))->withErrors($e->errors());
        }

        return redirect()->away((string) $checkout->url);
    }

    public function back(PaymentCheckout $checkout, SettleCheckout $settle, PayLinks $links): RedirectResponse
    {
        try {
            $checkout = $settle->handle($checkout);
        } catch (Throwable $e) {
            report($e);     // the webhook or the sweeper settles it later; the payer sees "confirming"
        }

        if ($checkout->channel === 'family' && auth()->check()) {
            return redirect()->to(FamilyBilling::getUrl(['checkout' => $checkout->id], panel: 'family'));
        }

        return redirect()->to($links->invoice($checkout->invoice()->firstOrFail(), $checkout->payer_id, ['checkout' => $checkout->id]));
    }

    /** The page in the payer's language and clock, else the academy's. */
    private function speak(?string $payerId, TenantContext $tenants): void
    {
        $payer = $payerId ? DB::table('users')->where('id', $payerId)->first(['locale', 'time_format']) : null;
        app()->setLocale($payer?->locale ?: $tenants->require()->defaultLocale);
        app(ViewerClock::class)->choose($payer?->time_format);
    }
}
