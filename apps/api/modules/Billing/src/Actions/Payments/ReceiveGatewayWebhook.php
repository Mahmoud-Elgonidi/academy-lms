<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\GatewayWebhookItem;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\GatewayManager;
use Throwable;

/**
 * A gateway's event for one of the academy's accounts: checked (signature or
 * shared secret), kept once in the inbox, then the checkout it is about is
 * settled by asking the gateway (SettleCheckout). A repeated delivery does
 * nothing; an event about nothing of ours is kept and left alone.
 */
final class ReceiveGatewayWebhook
{
    public function __construct(private readonly GatewayManager $gateways, private readonly SettleCheckout $settle) {}

    /** False when the delivery is not genuine. */
    public function handle(GatewayAccount $account, Request $request): bool
    {
        $event = $this->gateways->for($account)->parseWebhook($request);
        if ($event === null) {
            return false;
        }

        try {
            $item = GatewayWebhookItem::query()->create([
                'gateway_account_id' => $account->id, 'event_id' => mb_substr($event->id, 0, 190), 'event' => mb_substr($event->type, 0, 80),
                'provider_ref' => $event->ref, 'payload' => $event->payload, 'received_at' => CarbonImmutable::now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return true;    // delivered before
        }

        $checkout = $event->ref === null ? null
            : PaymentCheckout::query()->where('gateway_account_id', $account->id)->where('provider_ref', $event->ref)->first();
        $checkout ??= ($id = $event->payload['data']['metadata']['checkout_id'] ?? null) && Str::isUuid($id)
            ? PaymentCheckout::query()->where('gateway_account_id', $account->id)->find($id) : null;

        try {
            if ($checkout !== null) {
                $this->settle->handle($checkout);
            }
            $item->update(['processed_at' => CarbonImmutable::now()]);
        } catch (Throwable $e) {
            $item->update(['error' => mb_substr($e->getMessage(), 0, 490)]);   // the sweeper asks the gateway again later
            report($e);
        }

        return true;
    }
}
