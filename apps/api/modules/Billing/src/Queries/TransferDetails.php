<?php

namespace Modules\Billing\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;

/**
 * Where a payer sends the money by hand (docs/architecture/02, section 3):
 * the academy's own words (a bank, the account holder and the IBAN, an
 * InstaPay or wallet number), plain text of several lines. A price region's
 * own text wins for the payers in it (their billing account's region); the
 * academy's is everyone else's. An invoice has them only while something is
 * left to pay on it, and a payer can send a proof only where they have them.
 * Shown as text, never as HTML.
 */
final class TransferDetails
{
    /** The longest text the academy may write, in characters. */
    public const MAX = 1000;

    /** The academy's own text, for every payer, if it wrote one. */
    public function academy(): ?string
    {
        return self::clean(BillingSettings::current()->manual_payment_note);
    }

    /** What the payers in a region read: its own text, else the academy's. */
    public function forRegion(?string $regionId): ?string
    {
        $own = $regionId === null ? null : DB::table('price_regions')->where('id', $regionId)->value('transfer_details');

        return self::clean($own) ?? $this->academy();
    }

    /** For one invoice: none once nothing is left to pay on it. */
    public function forInvoice(Invoice $invoice): ?string
    {
        return $this->forInvoices([$invoice])[$invoice->id] ?? null;
    }

    /**
     * @param  iterable<Invoice>  $invoices
     * @return array<string, ?string> invoice id => where to send the money for it (null once nothing is left to pay)
     */
    public function forInvoices(iterable $invoices): array
    {
        $invoices = collect($invoices);
        $owing = $invoices->filter(fn (Invoice $i) => self::owes($i));
        if ($owing->isEmpty()) {
            return $invoices->mapWithKeys(fn (Invoice $i) => [$i->id => null])->all();
        }
        $regions = DB::table('billing_accounts as ba')
            ->join('price_regions as r', fn ($j) => $j->on('r.academy_id', '=', 'ba.academy_id')->on('r.id', '=', 'ba.price_region_id'))
            ->whereIn('ba.id', $owing->pluck('billing_account_id')->unique()->values()->all())
            ->pluck('r.transfer_details', 'ba.id');
        $academy = $this->academy();

        return $invoices->mapWithKeys(fn (Invoice $i) => [
            $i->id => self::owes($i) ? (self::clean($regions[$i->billing_account_id] ?? null) ?? $academy) : null,
        ])->all();
    }

    /**
     * The one text a payer's invoices go by (for earlier app versions, which
     * show a single text): their region's, or the academy's when they have no
     * billing account yet; none when their regions read different ones.
     */
    public function forPayer(string $payerId): ?string
    {
        $regions = DB::table('billing_accounts')->where('payer_id', $payerId)->distinct()->pluck('price_region_id');
        if ($regions->isEmpty()) {
            return $this->academy();
        }
        $texts = $regions->map(fn ($region) => $this->forRegion((string) $region))->unique()->values();

        return $texts->count() === 1 ? $texts->first() : null;
    }

    /** The text as it is kept and shown: plain line ends, no blank edges; none when nothing is left. */
    public static function clean(?string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));

        return $text === '' ? null : $text;
    }

    private static function owes(Invoice $invoice): bool
    {
        return $invoice->status === 'issued' && $invoice->total_minor - $invoice->paid_minor > 0;
    }
}
