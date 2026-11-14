<?php

namespace Modules\Platform\Actions\Recharging;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Plans\Requests\PlanRequests;

/**
 * The academy removes its saved card (Settings › Usage and balance): its
 * owner or an admin, never while the platform team is signed in as someone.
 * The card is let go here and on Stripe, and automatic recharge of every
 * meter turns off with it (nothing left to charge). Audited (the brand and
 * last four only).
 */
final class RemovePlatformCard
{
    public function __construct(
        private readonly PlanRequests $requests,
        private readonly PlatformAudit $audit,
        private readonly StripeClient $stripe,
    ) {}

    /** @throws ValidationException */
    public function handle(): void
    {
        $academy = $this->requests->authorizeAcademy();
        $card = DB::connection('pgsql_platform')->transaction(function () use ($academy) {
            $card = PlatformPaymentMethod::query()->live()->where('academy_id', $academy->id)->lockForUpdate()->first();
            if ($card === null) {
                throw ValidationException::withMessages(['card' => __('platform::recharge.errors.no_card')]);
            }
            $card->update(['removed_at' => now(), 'removed_reason' => 'removed']);
            AutoRecharge::query()->where('academy_id', $academy->id)->where('enabled', true)->update(['enabled' => false, 'off_reason' => 'no_card', 'off_at' => now(), 'updated_at' => now()]);
            $this->audit->record($academy->id, 'academy.platform_card_removed', 'platform_card', $card->id, ['brand' => $card->brand, 'last4' => $card->last4]);

            return $card;
        });
        if (StripeClient::configured()) {
            $this->stripe->detach($card->method_ref);
        }
    }
}
