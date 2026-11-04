<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Billing\Actions\Cards\SetAutoRenew;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\Cards\PayerCards;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerClock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The payer's saved card and automatic renewal, from the signed link in
 * "renewal coming" (J5b), without signing in: what is saved (brand, last four),
 * the charges coming, and one button that turns automatic renewal off. The
 * link only ever turns it off (turning it on, or saving a card, is done
 * signed in or while paying), so a link passed on can do no harm. Showing
 * the page changes nothing (a link preview opens it too).
 */
final class AutoRenewController
{
    public function show(Request $request, string $payer, PayerCards $cards, BrandingApi $branding, TenantContext $tenants): View
    {
        $this->speak($payer, $tenants);
        abort_unless(DB::table('billing_accounts')->where('payer_id', $payer)->exists(), 404);

        return view('billing::pay.auto-renew', [
            'branding' => $branding->current(),
            'state' => $cards->present($payer),
            'off' => URL::temporarySignedRoute('billing.auto-renew.off', now()->addHours(6), ['payer' => $payer], absolute: false),
        ]);
    }

    public function off(Request $request, string $payer, SetAutoRenew $set): RedirectResponse
    {
        abort_unless(DB::table('billing_accounts')->where('payer_id', $payer)->exists(), 404);
        $set->handle($payer, false, null, 'link');

        return redirect()->to(app(PayLinks::class)->autoRenew($payer))->with('auto_renew_off', true);
    }

    /** The page in the payer's language, zone and clock, else the academy's. */
    private function speak(string $payerId, TenantContext $tenants): void
    {
        $payer = DB::table('users')->where('id', $payerId)->first(['locale', 'time_format', 'timezone']);
        app()->setLocale($payer?->locale ?: $tenants->require()->defaultLocale);
        app(ViewerClock::class)->choose($payer?->time_format);
        app(ViewerTimezone::class)->setDevice($payer?->timezone ?: null);
    }
}
