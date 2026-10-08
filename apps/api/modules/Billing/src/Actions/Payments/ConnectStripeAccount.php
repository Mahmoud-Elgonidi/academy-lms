<?php

namespace Modules\Billing\Actions\Payments;

use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\StripeConnect;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * The academy's own Stripe account, connected to the platform (Stripe
 * Connect Standard): the code Stripe sent back becomes the account's id,
 * kept as a gateway account that needs no keys. Connecting the same Stripe
 * account again turns it back on.
 */
final class ConnectStripeAccount
{
    public function __construct(
        private readonly StripeConnect $connect,
        private readonly AcademyPlan $plan,
    ) {}

    public function handle(string $code, ?string $actorId): GatewayAccount
    {
        // Taking payments online is an area of the academy's plan.
        $this->plan->requireFeature(Feature::OnlinePayments);
        ['account' => $accountId, 'live' => $live] = $this->connect->exchange($code);

        $account = GatewayAccount::query()->where('gateway', 'stripe_connect')->where('external_id', $accountId)->first()
            ?? new GatewayAccount(['gateway' => 'stripe_connect', 'external_id' => $accountId, 'label' => 'Stripe', 'credentials' => [], 'created_by' => $actorId,
                'sort' => (int) GatewayAccount::query()->max('sort') + 1]);
        $account->fill(['mode' => $live ? 'live' : 'test', 'is_active' => true])->save();
        app(AuditTrail::class)->record('billing.stripe_connected', 'gateway_account', $account->id, ['account' => $accountId, 'mode' => $account->mode]);

        return $account;
    }
}
