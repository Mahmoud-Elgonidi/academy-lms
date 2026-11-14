<?php

namespace Modules\Platform\Actions\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Platform\Models\Recharging\PlatformWebhookItem;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\StripeClient;
use Throwable;

/**
 * Stripe's event about the platform's own account (a pack's Checkout, a
 * charge on a saved card): refused unless Stripe signed it with the platform
 * webhook's secret, kept once in the inbox by its id, then it only triggers
 * SettleTopUpPayment, which asks Stripe itself. A repeated delivery does
 * nothing; an event about nothing of ours is kept and left alone.
 */
final class ReceivePlatformWebhook
{
    public function __construct(private readonly SettleTopUpPayment $settle) {}

    /** False when the delivery is not genuine. */
    public function handle(Request $request): bool
    {
        $event = StripeClient::verified($request, (string) config('platform.payments.stripe.webhook_secret'));
        if ($event === null) {
            return false;
        }
        $object = (array) ($event['data']['object'] ?? []);
        $type = (string) $event['type'];
        $ref = str_starts_with($type, 'checkout.session.') || str_starts_with($type, 'payment_intent.') ? ($object['id'] ?? null) : null;

        try {
            $item = PlatformWebhookItem::query()->create([
                'event_id' => mb_substr((string) $event['id'], 0, 190), 'event' => mb_substr($type, 0, 80),
                'ref' => is_string($ref) ? mb_substr($ref, 0, 120) : null, 'payload' => $event, 'received_at' => CarbonImmutable::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return true;    // delivered before
        }

        $purchaseId = $object['metadata']['purchase'] ?? null;
        $purchase = is_string($purchaseId) && Str::isUuid($purchaseId) ? TopUpPurchase::query()->find($purchaseId) : null;
        if ($purchase === null && is_string($ref)) {
            $purchase = TopUpPurchase::query()->where('provider_ref', $ref)->orWhere('payment_ref', $ref)->first();
        }
        // Whatever the event says, the purchase is settled by asking Stripe about its own session or PaymentIntent.

        try {
            if ($purchase !== null) {
                $this->settle->handle($purchase);
            }
            $item->update(['processed_at' => CarbonImmutable::now()]);
        } catch (Throwable $e) {
            $item->update(['error' => mb_substr($e->getMessage(), 0, 490)]);   // the hourly sweep asks Stripe again
            report($e);
        }

        return true;
    }
}
