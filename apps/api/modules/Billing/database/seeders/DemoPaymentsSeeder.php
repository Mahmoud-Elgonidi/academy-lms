<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\Gateways\DemoGateway;

/**
 * Paying online in the demo, without keys: the demo gateway (its own pretend
 * page), how to pay by hand, and one declined try on Omar's invoice for the
 * history. The invoices stay unpaid so you can pay one yourself from
 * /my/account or from the link in the invoice message.
 */
final class DemoPaymentsSeeder extends Seeder
{
    public function run(SaveGatewayAccount $save, StartCheckout $start, SettleCheckout $settle, GatewayManager $gateways): void
    {
        if (! $gateways->demoAllowed()) {
            return;
        }
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');

        $account = $save->handle(null, ['gateway' => 'demo', 'label' => 'كارت أو مدى (تجريبي)', 'mode' => 'test'], $owner);
        $settings = BillingSettings::current();
        $settings->manual_payment_note = "تحويل بنكي: مصرف الراجحي · SA44 2000 0001 2345 6789 1234 · أكاديمية النور\nأو STC Pay على 0500000000، وابعتوا صورة الإيصال على واتساب الأكاديمية.";
        $settings->save();

        $omar = DB::table('users')->where('email', 'omar.guardian@demo.test')->value('id');
        $invoice = Invoice::query()->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'invoices.academy_id')->on('ba.id', '=', 'invoices.billing_account_id'))
            ->where('ba.payer_id', $omar)->where('invoices.status', 'issued')->first(['invoices.*']);
        if ($invoice !== null) {
            $declined = $start->handle($invoice, $account, $omar, 'link');
            DemoGateway::decide((string) $declined->provider_ref, pay: false);
            $settle->handle($declined);
        }
    }
}
