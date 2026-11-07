<?php

namespace Modules\Billing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\SetInvoiceDue;
use Modules\Billing\Models\Invoice;

/**
 * The portal's balance test reads Hala's waiting invoice as due soon (within
 * a day and a half). The demo makes it due "tomorrow at 18:00 on her clock"
 * (DemoCollectionSeeder), which is up to 42 hours away just after her
 * midnight; the end-to-end reset puts it 20 hours ahead instead, so the test
 * reads the same at whatever hour it runs. E2eResetSeeder calls this inside
 * the demo academy.
 */
final class E2eCollection
{
    public static function dueSoon(): void
    {
        $hala = DB::table('users')->where('email', 'hala.guardian@demo.test')->value('id');
        if ($hala === null) {
            return;
        }
        $invoice = Invoice::query()
            ->whereHas('billingAccount', fn ($q) => $q->where('payer_id', $hala))
            ->where('status', 'issued')->where('paid_minor', 0)
            ->whereNotExists(fn ($q) => $q->from('manual_payment_proofs as p')->whereColumn('p.academy_id', 'invoices.academy_id')
                ->whereColumn('p.invoice_id', 'invoices.id')->where('p.status', 'pending'))
            ->orderByDesc('issued_at')->first();
        if ($invoice !== null) {
            app(SetInvoiceDue::class)->handle($invoice->id, CarbonImmutable::now()->addHours(20));
        }
    }
}
