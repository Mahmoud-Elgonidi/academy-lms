<?php

namespace Modules\Billing\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Zatca\ZatcaQr;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * VAT, the invoice number prefix, how many days an invoice is due in, the
 * name the academy is registered under for tax, and whether its invoices
 * are Saudi simplified tax invoices (ZATCA, phase 1), which takes a valid
 * Saudi VAT number. Invoices already issued keep what they said.
 */
final class SaveBillingSettings
{
    /** @param  ?bool  $zatca  null leaves it as it is */
    public function handle(float|int|string $taxPercent, bool $pricesIncludeTax, ?string $taxNumber, string $invoicePrefix, int $dueDays, ?string $sellerName = null, ?bool $zatca = null): BillingSettings
    {
        $bp = (int) round((float) $taxPercent * 100);
        if ($bp < 0 || $bp > 10_000 || $dueDays < 0 || $dueDays > 90 || ! preg_match('/^[A-Za-z0-9\-\/]{0,12}$/', $invoicePrefix)) {
            throw ValidationException::withMessages(['tax_percent' => __('billing::plans.errors.settings')]);
        }
        $taxNumber = $taxNumber === null ? null : preg_replace('/\s+/', '', $taxNumber);
        $sellerName = $sellerName === null ? null : trim($sellerName);
        $zatca ??= BillingSettings::current()->zatca;
        if ($zatca && ! ZatcaQr::validVatNumber($taxNumber)) {
            throw ValidationException::withMessages(['tax_number' => __('billing::plans.errors.vat_number')]);
        }
        if ($sellerName !== null && mb_strlen($sellerName) > 120) {
            throw ValidationException::withMessages(['seller_name' => __('billing::plans.errors.settings')]);
        }

        $settings = BillingSettings::current();
        $settings->fill([
            'tax_rate_bp' => $bp,
            'prices_include_tax' => $pricesIncludeTax,
            'tax_number' => $taxNumber ?: null,
            'invoice_prefix' => $invoicePrefix,
            'due_days' => $dueDays,
            'seller_name' => $sellerName ?: ($settings->seller_name ?? null),
            'zatca' => $zatca,
        ])->save();
        if ($settings->wasChanged()) {
            app(AuditTrail::class)->record('billing.settings_saved', null, null, ['tax_percent' => $bp / 100, 'prices_include_tax' => $pricesIncludeTax, 'invoice_prefix' => $invoicePrefix, 'due_days' => $dueDays, 'zatca' => $zatca]);
        }

        return $settings;
    }
}
