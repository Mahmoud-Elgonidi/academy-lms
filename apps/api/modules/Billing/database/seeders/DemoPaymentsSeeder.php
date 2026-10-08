<?php

namespace Modules\Billing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\Gateways\DemoGateway;

/**
 * Paying online in the demo, without keys: the demo gateway (its own pretend
 * page), how to pay by hand, and one declined try on Omar's invoice for the
 * history, after which his father paid by bank transfer and sent the receipt
 * (a proof waiting under "Invoices"). The other invoices stay unpaid so you
 * can pay one yourself, online or with a proof, from /my/account, the app,
 * the portal or the link in the invoice message.
 */
final class DemoPaymentsSeeder extends Seeder
{
    public function run(SaveGatewayAccount $save, StartCheckout $start, SettleCheckout $settle, GatewayManager $gateways, SubmitPaymentProof $proofs): void
    {
        if (! $gateways->demoAllowed()) {
            return;
        }
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');

        $account = $save->handle(null, ['gateway' => 'demo', 'label' => 'كارت أو مدى (تجريبي)', 'mode' => 'test'], $owner);
        $settings = BillingSettings::current();
        $settings->manual_payment_note = "تحويل بنكي: مصرف الراجحي · SA44 2000 0001 2345 6789 1234 · أكاديمية النور\nأو STC Pay على 0500000000، وبعدين اضغطوا «دفعت بالتحويل» وابعتوا صورة الإيصال.";
        $settings->save();

        $omar = DB::table('users')->where('email', 'omar.guardian@demo.test')->value('id');
        $invoice = Invoice::query()->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'invoices.academy_id')->on('ba.id', '=', 'invoices.billing_account_id'))
            ->where('ba.payer_id', $omar)->where('invoices.status', 'issued')->first(['invoices.*']);
        if ($invoice !== null) {
            $declined = $start->handle($invoice, $account, $omar, 'link');
            DemoGateway::decide((string) $declined->provider_ref, pay: false);
            $settle->handle($declined);

            // The card was declined, so he paid by transfer the next day and sent the receipt (our own picture, files/demo-receipt.html).
            $copy = tempnam(sys_get_temp_dir(), 'receipt');
            copy(__DIR__.'/files/demo-receipt.jpg', $copy);
            $proofs->handle($invoice->refresh(), $omar, 'family', 'bank_transfer', $invoice->outstanding()->decimal(),
                CarbonImmutable::now('Asia/Qatar')->subDay()->toDateString(), 'TRF-58213', 'حوّلت من حساب الراجحي بعد ما الكارت اترفض.',
                new UploadedFile($copy, 'إيصال التحويل.jpg', 'image/jpeg', null, true));
            @unlink($copy);
        }
    }
}
