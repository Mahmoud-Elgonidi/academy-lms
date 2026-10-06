<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Billing\Payments\PayLinks;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * An invoice and its ways to pay, for the payer, in the academy's look and
 * without signing in (the signed link in the invoice message): the online
 * gateways that take its currency, or how to pay by hand. Back from a
 * gateway, the checkout is settled by asking the gateway, then the payer
 * sees where it stands.
 */
final class PayController
{
    public function show(Request $request, Invoice $invoice, OnlinePayments $online, BrandingApi $branding, TenantContext $tenants): View
    {
        $payerId = $request->query('u');
        $this->speak($payerId, $tenants);
        $checkout = $request->query('checkout') ? PaymentCheckout::query()->where('invoice_id', $invoice->id)->find($request->query('checkout')) : null;
        $student = DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('s.id', $invoice->subscription_id)->value('u.name');

        return view('billing::pay.show', [
            'invoice' => $invoice,
            'student' => $student,
            'branding' => $branding->current(),
            'checkout' => $checkout,
            'options' => $invoice->status === 'issued' ? array_map(fn (GatewayAccount $account) => [
                'account' => $account,
                'url' => URL::temporarySignedRoute('billing.pay.start', now()->addHours(2), array_filter(['invoice' => $invoice->id, 'account' => $account->id, 'u' => $payerId]), absolute: false),
            ], $online->accountsFor($invoice)) : [],
            'manual' => $online->manualNote(),
            'tz' => $tenants->require()->defaultTimezone,
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

    /** The page in the payer's language, else the academy's. */
    private function speak(?string $payerId, TenantContext $tenants): void
    {
        $locale = $payerId ? DB::table('users')->where('id', $payerId)->value('locale') : null;
        app()->setLocale($locale ?: $tenants->require()->defaultLocale);
    }
}
