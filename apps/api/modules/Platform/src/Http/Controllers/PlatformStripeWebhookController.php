<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Actions\Recharging\ReceivePlatformWebhook;

/**
 * Where Stripe posts events about the platform's own account
 * (/webhooks/platform/stripe; set it in Stripe › Developers › Webhooks and its
 * signing secret in console › Platform settings › Stripe). No academy and no
 * session: the purchase is found from the event, and Stripe is asked.
 */
final class PlatformStripeWebhookController
{
    public function __invoke(Request $request, ReceivePlatformWebhook $receive): JsonResponse
    {
        return $receive->handle($request)
            ? response()->json(['received' => true])
            : response()->json(['received' => false], 401);
    }
}
