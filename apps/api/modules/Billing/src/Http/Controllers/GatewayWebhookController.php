<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Billing\Actions\Payments\ReceiveGatewayWebhook;
use Modules\Billing\Models\GatewayAccount;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantResolver;

/**
 * Where a gateway posts events for one of an academy's accounts
 * (/webhooks/payments/{academy}/{account}): the academy and the account
 * come from the path, so the tenant and the secret are known before the
 * signature is checked (docs/architecture/03, section 5).
 */
final class GatewayWebhookController
{
    public function __invoke(Request $request, string $academy, string $account, TenantResolver $tenants, TenantContext $context, ReceiveGatewayWebhook $receive): JsonResponse
    {
        $tenant = Str::isUuid($academy) && Str::isUuid($account) ? $tenants->byId($academy) : null;
        if ($tenant === null) {
            return response()->json(['received' => false], 404);
        }

        $genuine = $context->run($tenant, function () use ($account, $request, $receive) {
            $gatewayAccount = GatewayAccount::query()->find($account);

            return $gatewayAccount === null ? null : $receive->handle($gatewayAccount, $request);
        });

        return match ($genuine) {
            null => response()->json(['received' => false], 404),
            false => response()->json(['received' => false], 401),
            default => response()->json(['received' => true]),
        };
    }
}
