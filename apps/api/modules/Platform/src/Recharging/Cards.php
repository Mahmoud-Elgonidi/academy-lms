<?php

namespace Modules\Platform\Recharging;

use Carbon\CarbonImmutable;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Payments\StripeCard;

/**
 * The academy's saved card, kept only from Stripe's own answer after a
 * Checkout the academy ticked "save my card" on: a new one takes the old
 * one's place (one live a academy). Call it inside the settling transaction.
 */
final class Cards
{
    public function keep(string $academyId, StripeCard $card, ?string $savedBy): PlatformPaymentMethod
    {
        $now = CarbonImmutable::now();
        PlatformPaymentMethod::query()->live()->where('academy_id', $academyId)->update(['removed_at' => $now, 'removed_reason' => 'replaced', 'updated_at' => $now]);

        return PlatformPaymentMethod::query()->create([
            'academy_id' => $academyId,
            'customer_ref' => $card->customer,
            'method_ref' => $card->paymentMethod,
            'brand' => $card->brand !== '' ? $card->brand : 'card',
            'last4' => str_pad(substr($card->last4, -4), 4, '0', STR_PAD_LEFT),
            'exp_month' => max(1, min(12, $card->expMonth)),
            'exp_year' => $card->expYear,
            'saved_by' => $savedBy,
        ]);
    }
}
