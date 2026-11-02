<?php

namespace Modules\Billing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\SetInvoiceDue;
use Modules\Billing\Domain\Collections\CollectionRules;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\Invoice;

/**
 * The collection timeline in the demo (docs/architecture/02, section 27, J5a):
 * Hala's family invoice (DemoFamilyBillingSeeder), waiting to be paid with
 * nothing on it, falls due tomorrow at 18:00 on her clock (Riyadh), so the
 * next hourly billing:follow-up from nine sends her "due tomorrow", then
 * "due today", and two days late the office hears it. Once (a marker step);
 * nothing when her invoice is paid, has a receipt waiting, or is not there.
 * Runs in the fresh seeding after family billing, and in DemoCatchUpSeeder.
 */
final class DemoCollectionSeeder extends Seeder
{
    public const MARKER = 'demo:j5a';

    public function run(SetInvoiceDue $due): void
    {
        if (CollectionStep::query()->where('key', 'like', self::MARKER.':%')->exists()) {
            return;
        }
        $hala = DB::table('users')->where('email', 'hala.guardian@demo.test')->first(['id', 'timezone']);
        if ($hala === null) {
            return;
        }
        $invoice = Invoice::query()
            ->whereHas('billingAccount', fn ($q) => $q->where('payer_id', $hala->id))
            ->where('status', 'issued')->where('paid_minor', 0)
            ->whereNotExists(fn ($q) => $q->from('manual_payment_proofs as p')->whereColumn('p.academy_id', 'invoices.academy_id')
                ->whereColumn('p.invoice_id', 'invoices.id')->where('p.status', 'pending'))
            ->orderByDesc('issued_at')->first();
        if ($invoice === null) {
            return;
        }
        $tz = $hala->timezone ?: 'Asia/Riyadh';
        $due->handle($invoice->id, CarbonImmutable::now()->setTimezone($tz)->addDay()->setTime(18, 0)->utc());
        CollectionStep::claim(self::MARKER.':'.$invoice->id, ['invoice_id' => $invoice->id, 'payer_id' => $hala->id, 'step' => CollectionRules::ISSUED]);
    }
}
