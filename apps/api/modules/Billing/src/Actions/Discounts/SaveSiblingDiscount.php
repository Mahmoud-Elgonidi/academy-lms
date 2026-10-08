<?php

namespace Modules\Billing\Actions\Discounts;

use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The family discount (docs/architecture/02, section 3, "خصم الإخوات"): a
 * percentage off the second child's invoices, and another from the third
 * child on; 0 turns it off. Invoices already issued keep what they said.
 */
final class SaveSiblingDiscount
{
    public function handle(float|int|string $secondPercent, float|int|string $morePercent): BillingSettings
    {
        $second = (int) round((float) $secondPercent * 100);
        $more = (int) round((float) $morePercent * 100);
        if ($second < 0 || $second > 10_000 || $more < 0 || $more > 10_000) {
            throw ValidationException::withMessages(['second' => __('billing::discounts.errors.sibling')]);
        }

        $settings = BillingSettings::current();
        $settings->fill(['sibling_second_bp' => $second, 'sibling_more_bp' => $more])->save();
        if ($settings->wasChanged() || $settings->wasRecentlyCreated) {
            app(AuditTrail::class)->record('billing.sibling_discount_saved', null, null, ['second' => $second / 100, 'more' => $more / 100]);
        }

        return $settings;
    }
}
