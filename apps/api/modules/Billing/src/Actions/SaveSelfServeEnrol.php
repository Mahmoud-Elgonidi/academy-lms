<?php

namespace Modules\Billing\Actions;

use Modules\Billing\Models\BillingSettings;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Whether families may start a course and pay on their own from their
 * account (docs/architecture/02, section 27, J3; on at first). Off, the
 * start flow says the office enrols and how to reach it; courses already
 * started go on as they are. Audited with the choice.
 */
final class SaveSelfServeEnrol
{
    public function handle(bool $on): BillingSettings
    {
        $settings = BillingSettings::current();
        $before = $settings->exists ? $settings->self_serve_enrol : null;
        $by = AuditActor::current();
        $settings->fill(['self_serve_enrol' => $on, 'self_serve_saved_by' => $by->type === 'user' ? $by->id : $settings->self_serve_saved_by])->save();
        if ($before !== $on) {
            app(AuditTrail::class)->record($on ? 'billing.self_serve_on' : 'billing.self_serve_off');
        }

        return $settings;
    }
}
