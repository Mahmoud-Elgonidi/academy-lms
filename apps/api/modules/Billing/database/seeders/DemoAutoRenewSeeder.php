<?php

namespace Modules\Billing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Domain\Collections\CollectionRules;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Queries\Renewals\NextRenewals;

/**
 * Automatic renewal in the demo (docs/architecture/02, section 27, J5b): the
 * family whose next renewal is nearest (a course running, its block paid,
 * nothing waiting; not Hala's family, mid-way through the collection
 * timeline, nor Omar's, with a receipt waiting) has a demo card saved
 * (Visa ****4242, 12/2034) and automatic renewal on, so when the renewal is
 * issued billing:follow-up tells them three days ahead and the demo gateway
 * charges it. Once (a marker step); nothing without the demo gateway. Runs
 * in the fresh seeding after the collection timeline, and in DemoCatchUpSeeder.
 */
final class DemoAutoRenewSeeder extends Seeder
{
    public const MARKER = 'demo:j5b';

    public function run(GatewayManager $gateways, NextRenewals $renewals): void
    {
        if (! $gateways->demoAllowed() || CollectionStep::query()->where('key', 'like', self::MARKER.':%')->exists()) {
            return;
        }
        $account = GatewayAccount::query()->where('gateway', 'demo')->where('is_active', true)->first();
        if ($account === null) {
            return;
        }
        $skip = DB::table('users')->whereIn('email', ['hala.guardian@demo.test', 'omar.guardian@demo.test'])->pluck('id')->all();
        $subscriptions = Subscription::query()->with(['plan', 'billingAccount'])
            ->where('status', 'active')->where('block_no', '>=', 1)->taught()
            ->whereHas('billingAccount', fn ($q) => $q->whereNotIn('payer_id', $skip))
            ->get()->filter(fn (Subscription $s) => ! $s->hasWaitingBlock() && $account->takes($s->currency));
        $next = $renewals->of($subscriptions);
        $chosen = $subscriptions->filter(fn (Subscription $s) => isset($next[$s->id]) && $next[$s->id]->days >= 1)
            ->sortBy(fn (Subscription $s) => $next[$s->id]->days)->first();
        if ($chosen === null) {
            return;
        }
        $payerId = $chosen->billingAccount->payer_id;
        $now = CarbonImmutable::now();

        PaymentMethod::query()->create([
            'payer_id' => $payerId, 'gateway_account_id' => $account->id, 'gateway' => 'demo',
            'tokens' => ['token' => 'demo_tok_'.bin2hex(random_bytes(8))],
            'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2034,
            'mandate_version' => Mandate::VERSION, 'mandate_hash' => Mandate::hash('ar'),
        ]);
        BillingAccount::query()->where('payer_id', $payerId)->update(['auto_renew' => true, 'auto_renew_changed_at' => $now->subDay()]);
        CollectionStep::claim(self::MARKER.':'.$chosen->id, ['subscription_id' => $chosen->id, 'payer_id' => $payerId, 'step' => CollectionRules::ISSUED]);
    }
}
