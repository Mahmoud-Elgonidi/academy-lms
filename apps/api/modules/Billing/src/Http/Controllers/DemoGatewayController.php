<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;

/** The demo gateway's pretend page: pay or decline, then back to the academy, as from a real gateway. */
final class DemoGatewayController
{
    public function show(string $ref, GatewayManager $gateways, TenantContext $tenants): View
    {
        abort_unless($gateways->demoAllowed(), 404);
        app()->setLocale($tenants->require()->defaultLocale);
        $state = DemoGateway::state($ref) ?? abort(404);

        return view('billing::pay.demo-gateway', [
            'state' => $state,
            'amount' => Money::of($state['minor'], $state['currency']),
            'pay' => URL::temporarySignedRoute('billing.demo-gateway.decide', now()->addHour(), ['ref' => $ref, 'pay' => 1], absolute: false),
            'decline' => URL::temporarySignedRoute('billing.demo-gateway.decide', now()->addHour(), ['ref' => $ref, 'pay' => 0], absolute: false),
        ]);
    }

    public function decide(Request $request, string $ref, GatewayManager $gateways): RedirectResponse
    {
        abort_unless($gateways->demoAllowed(), 404);
        $back = DemoGateway::decide($ref, $request->boolean('pay')) ?? abort(404);

        return redirect()->away($back);
    }
}
