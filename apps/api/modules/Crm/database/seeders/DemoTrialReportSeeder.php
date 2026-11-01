<?php

namespace Modules\Crm\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Crm\Actions\IssueTrialOffer;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadTrial;

/**
 * After the trial in the demo (docs/architecture/02, section 27, J2): the
 * academy sends an offer with the trial's report (10% for 72 hours) and
 * gives a free second trial with another teacher; Jana's assessed Tajweed
 * trial (DemoCrmSeeder) reads as a report for her mother, with what the
 * family reads filled in and an open offer, so the report can be shown.
 * Once: settings the demo's owner saved stay as they are, and a demo whose
 * trial has an offer already is left alone (DemoCatchUpSeeder runs this).
 */
final class DemoTrialReportSeeder extends Seeder
{
    public function run(IssueTrialOffer $offer): void
    {
        if (LeadTrial::query()->whereNotNull('offer_coupon_id')->exists()) {
            return;
        }
        $settings = CrmSettings::query()->first();
        if ($settings === null || $settings->updated_by === null) {
            ($settings ?? new CrmSettings)->fill(['trial_offer' => true, 'trial_offer_percent' => 10, 'trial_offer_hours' => 72, 'second_trial' => true])->save();
        }
        $jana = Lead::query()->where('contact_name', 'أم جنى')->where('learner_name', 'جنى')->value('id');
        $trial = $jana === null ? null : LeadTrial::query()->with('lead')->where('lead_id', $jana)->where('status', 'attended')->whereNotNull('assessed_at')->first();
        if ($trial === null) {
            return;
        }
        $trial->forceFill([
            'strengths' => $trial->strengths ?? 'تقرأ بصوت واضح وثقة، وتحفظ بسرعة، وتسأل حين لا تفهم.',
            'to_work_on' => $trial->to_work_on ?? 'أحكام النون الساكنة والتنوين، والمدود، وأن تتمهّل في آخر الآية.',
            'family_note' => $trial->family_note ?? 'سعدت بالتعرف إلى جنى. أساسها جيد، وبحصتين في الأسبوع ستتقن أحكام المستوى الأول خلال شهرين بإذن الله.',
            'report_sent_at' => $trial->report_sent_at ?? CarbonImmutable::now(),
        ])->save();
        $offer->handle($trial->refresh());
    }
}
