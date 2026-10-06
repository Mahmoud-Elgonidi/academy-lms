<?php

namespace Modules\Billing\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingSettings;

/** VAT, the invoice number prefix and how many days an invoice is due in. */
final class SaveBillingSettings
{
    public function handle(float|int|string $taxPercent, bool $pricesIncludeTax, ?string $taxNumber, string $invoicePrefix, int $dueDays): BillingSettings
    {
        $bp = (int) round((float) $taxPercent * 100);
        if ($bp < 0 || $bp > 10_000 || $dueDays < 0 || $dueDays > 90 || ! preg_match('/^[A-Za-z0-9\-\/]{0,12}$/', $invoicePrefix)) {
            throw ValidationException::withMessages(['tax_percent' => __('billing::plans.errors.settings')]);
        }

        $settings = BillingSettings::current();
        $settings->fill([
            'tax_rate_bp' => $bp,
            'prices_include_tax' => $pricesIncludeTax,
            'tax_number' => $taxNumber ?: null,
            'invoice_prefix' => $invoicePrefix,
            'due_days' => $dueDays,
        ])->save();

        return $settings;
    }
}
