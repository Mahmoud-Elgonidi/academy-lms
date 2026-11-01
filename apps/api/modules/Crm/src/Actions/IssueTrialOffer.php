<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\CouponPurpose;
use Modules\Billing\Contracts\DTOs\PersonalCoupon;
use Modules\Crm\Domain\TrialOfferRules;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialReport;
use Modules\Crm\Support\TrialFacts;

/**
 * The academy's offer after a free trial (docs/architecture/02, section 27,
 * J2), made once with the trial's first assessment, while the academy has
 * one (crm_settings.trial_offer): a personal coupon through Billing
 * (BillingApi::issuePersonalCoupon: its percentage off the first invoice of
 * any plan, for the family's payer alone, once, until trial_offer_hours
 * from now; never on top of the family discount, the bigger applies). Only
 * for a trial that took place, a learner with no course in the subject that
 * has not ended, and never a second offer for the same learner and subject
 * (a second trial or a booking again makes none). What was promised stays:
 * its rate and end are kept on the trial, whatever the settings say later.
 */
final class IssueTrialOffer
{
    public function __construct(private readonly BillingApi $billing, private readonly TrialFacts $facts) {}

    /** @return ?string the coupon's id, or null when no offer is made */
    public function handle(LeadTrial $trial, ?CarbonImmutable $now = null): ?string
    {
        $settings = CrmSettings::current();
        $lead = $trial->lead;
        if (! $settings->trial_offer || $trial->offer_coupon_id !== null || ! TrialReport::ready($trial)
            || $lead->learner_id === null || $lead->contact_id === null) {
            return null;
        }
        $subjectId = (string) DB::table('sessions')->where('id', $trial->session_id)->value('subject_id');
        if ($this->facts->studying($lead->learner_id, $subjectId) || $this->facts->hadOffer($lead->learner_id, $subjectId)) {
            return null;
        }
        $now ??= CarbonImmutable::now();
        $endsAt = TrialOfferRules::endsAt($now, $settings->trial_offer_hours);
        $percentBp = $settings->trial_offer_percent * 100;

        $coupon = $this->billing->issuePersonalCoupon(new PersonalCoupon(
            purpose: CouponPurpose::TrialOffer,
            payerId: $lead->contact_id,
            percentBp: $percentBp,
            expiresAt: $endsAt,
            name: (string) __('crm::report.offer_coupon_name'),
            actorId: null,
        ));
        $trial->forceFill(['offer_coupon_id' => $coupon->id, 'offer_percent_bp' => $percentBp, 'offer_ends_at' => $endsAt])->save();
        LeadActivity::record($lead, 'offer_made', null, ['percent' => $settings->trial_offer_percent, 'until' => $endsAt->toIso8601ZuluString()], null);

        return $coupon->id;
    }
}
