<?php

namespace Modules\Platform\Actions\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\TopUps\ActivateTopUp;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Recharging\PlatformCustomer;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Payments\StripeException;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The academy buys a pack online (Settings › Usage and balance): its owner or
 * an admin, never while the platform team is signed in as someone, for a pack
 * priced in the academy's currency. A purchase is kept (open) and Stripe's
 * hosted Checkout page made for it, on the platform's own account (the
 * academy's Stripe customer, made once); optionally keeping the card for
 * automatic recharge. Nothing is added until Stripe says it was paid
 * (SettleTopUpPayment, from the webhook or the way back). Audited.
 */
final class StartTopUpCheckout
{
    /** How long the Checkout page stays open (Stripe's least is 30 minutes). */
    public const OPEN_MINUTES = 60;

    public function __construct(
        private readonly PlanRequests $requests,
        private readonly PlatformAudit $audit,
        private readonly PlatformAcademyPlan $plan,
        private readonly StripeClient $stripe,
    ) {}

    /**
     * @return array{purchase: TopUpPurchase, url: string} where to send the browser
     *
     * @throws ValidationException
     */
    public function handle(string $packId, bool $saveCard, string $userId): array
    {
        $tenant = $this->requests->authorizeAcademy();
        $academy = Academy::query()->findOrFail($tenant->id);
        $pack = TopUpPack::query()->offered()->find($packId);
        if ($pack === null) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_pack')]);
        }
        $limit = $pack->limitKind();
        if ($limit === Limit::AiUses && ! $this->plan->has(Feature::Ai)) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_ai')]);
        }
        if ($this->plan->limit($limit) === null) {
            throw ValidationException::withMessages(['pack' => __('platform::topups.errors.no_limit')]);
        }
        if (! StripeClient::configured()) {
            throw ValidationException::withMessages(['pack' => __('platform::recharge.errors.online_off')]);
        }
        $price = $pack->priceIn($academy->base_currency);
        if ($price === null) {
            throw ValidationException::withMessages(['pack' => __('platform::recharge.errors.no_price', ['currency' => $academy->base_currency])]);
        }

        $purchase = DB::connection('pgsql_platform')->transaction(function () use ($academy, $pack, $price, $saveCard, $userId) {
            $purchase = TopUpPurchase::query()->create([
                'academy_id' => $academy->id, 'pack_id' => $pack->id, 'limit' => $pack->limit, 'amount' => $pack->amount,
                'price_minor' => $price->minor, 'currency' => $price->currency, 'method' => TopUpPurchase::CARD,
                'save_card' => $saveCard, 'requested_by' => $userId,
            ]);
            $this->audit->record($academy->id, 'academy.topup_checkout_started', 'topup_purchase', $purchase->id, ['limit' => $pack->limit, 'amount' => $pack->amount]);

            return $purchase;
        });

        try {
            $back = ActivateTopUp::planUrl($academy);
            $session = $this->stripe->createCheckout(
                $purchase->id,
                $this->customer($academy),
                $price,
                $pack->name('en').' · '.__('shared::plans.limits.'.$pack->limit, [], 'en').' +'.number_format($pack->amount),
                $saveCard,
                $back.'?purchase='.$purchase->id,
                $back.'?purchase='.$purchase->id.'&cancelled=1',
                CarbonImmutable::now()->addMinutes(self::OPEN_MINUTES)->getTimestamp(),
                ['academy' => $academy->id, 'pack' => $pack->id, 'purchase' => $purchase->id],
            );
        } catch (StripeException $e) {
            report($e);
            $purchase->update(['status' => 'failed', 'failure' => 'processing_error', 'failed_at' => now()]);
            throw ValidationException::withMessages(['pack' => __('platform::recharge.errors.stripe')]);
        }
        $purchase->update(['provider_ref' => $session['id']]);

        return ['purchase' => $purchase, 'url' => $session['url']];
    }

    /** The academy as a customer of the platform's Stripe account, made once. */
    private function customer(Academy $academy): string
    {
        $known = PlatformCustomer::query()->find($academy->id);
        if ($known !== null) {
            return $known->customer_ref;
        }
        $id = app(StripeClient::class)->createCustomer($academy->id, $academy->name_en, ['academy' => $academy->id]);
        if (! str_starts_with($id, 'cus_')) {
            throw new StripeException('no_customer');
        }
        PlatformCustomer::query()->updateOrCreate(['academy_id' => $academy->id], ['customer_ref' => $id]);

        return $id;
    }
}
