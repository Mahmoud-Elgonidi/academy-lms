<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Billing\Actions\Payments\ConnectStripeAccount;
use Modules\Billing\Actions\Payments\ReceiveGatewayWebhook;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\StripeConnect;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * The platform's two Stripe Connect addresses: where Stripe sends an
 * academy's admin back after connecting (the academy comes from the
 * encrypted state, then they land on the academy's own payment settings),
 * and the one Connect webhook for every connected account (the academy from
 * the checkout's metadata, the account from the event).
 */
final class StripeConnectController
{
    public function connected(Request $request, StripeConnect $connect, TenantDirectory $directory, TenantContext $tenants, ConnectStripeAccount $action): RedirectResponse
    {
        $state = $connect->readState((string) $request->query('state'));
        $tenant = $state ? $directory->byId($state['academy']) : null;
        abort_if($tenant === null, 404);

        $outcome = 'denied';
        if ($request->filled('code') && ! $request->filled('error')) {
            try {
                $tenants->run($tenant, fn () => $action->handle((string) $request->query('code'), $state['user']));
                $outcome = 'connected';
            } catch (GatewayException $e) {
                report($e);
                $outcome = 'failed';
            }
        }
        $path = (string) parse_url(PaymentSettings::getUrl(panel: 'academy'), PHP_URL_PATH);

        return redirect()->away(rtrim($directory->baseUrl($tenant), '/').$path.'?stripe='.$outcome);
    }

    public function webhook(Request $request, TenantDirectory $directory, TenantContext $tenants, ReceiveGatewayWebhook $receive): JsonResponse
    {
        $event = StripeGateway::verified($request, (string) config('billing.payments.stripe.connect_webhook_secret'));
        if ($event === null) {
            return response()->json(['received' => false], 401);
        }
        $object = $event['data']['object'] ?? [];
        $academy = $object['metadata']['academy'] ?? null;
        $tenant = is_string($academy) && Str::isUuid($academy) ? $directory->byId($academy) : null;
        if ($tenant === null || empty($event['account'])) {
            return response()->json(['received' => true, 'ignored' => true]);   // not one of our checkouts
        }

        try {
            $tenants->run($tenant, function () use ($event, $request, $receive) {
                $account = GatewayAccount::query()->where('gateway', 'stripe_connect')->where('external_id', $event['account'])->first();
                if ($account !== null) {
                    $receive->handle($account, $request);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json(['received' => false], 500);   // Stripe tries again
        }

        return response()->json(['received' => true]);
    }
}
