<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialReport;
use Modules\Crm\Support\FamilyContact;
use Modules\Crm\Support\ReportLinks;
use Modules\Crm\Support\TrialFacts;

/**
 * The family hears that its trial's report is ready (crm.trial_report_ready,
 * docs/architecture/02, section 27, J2): the teacher's first name, the
 * subject, their own signed link to the report (ReportLinks, 30 days, no
 * sign-in) and, while the academy's offer is open and the learner has not
 * started, a line about it (its end is on the report). Once, after the first
 * assessment: an assessment corrected later changes the report, not the
 * messages. Whoever follows the lead may send it again (a new link), at most
 * once an hour. Only for a trial that took place and was assessed.
 */
final class SendTrialReport
{
    public function __construct(
        private readonly FamilyContact $family,
        private readonly ReportLinks $links,
        private readonly BillingApi $billing,
        private readonly TrialFacts $facts,
    ) {}

    /**
     * @param  bool  $again  sent by whoever follows the lead, after the first time
     * @return bool whether it went out
     *
     * @throws ValidationException on "trial" when sent again for a trial with no report
     */
    public function handle(LeadTrial $trial, ?string $actorId = null, bool $again = false): bool
    {
        if (! TrialReport::ready($trial)) {
            if ($again) {
                throw ValidationException::withMessages(['trial' => __('crm::trials.errors.no_report')]);
            }

            return false;
        }
        if (! $again && $trial->report_sent_at !== null) {
            return false;
        }
        $lead = $trial->lead;
        $session = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->where('s.id', $trial->session_id)->first(['s.subject_id', 'su.name_ar', 'su.name_en']);
        $offer = '';
        if ($trial->offer_coupon_id !== null && ! ($lead->learner_id !== null && $this->facts->studying($lead->learner_id, (string) $session?->subject_id))
            && $this->billing->personalCoupon($trial->offer_coupon_id)?->open() === true) {
            $offer = new Translatable('crm::notifications.offer_line', ['percent' => (string) ((int) $trial->offer_percent_bp / 100)]);
        }
        $now = CarbonImmutable::now();
        $sent = $this->family->send($lead, 'crm.trial_report_ready', [
            'student' => TrialReport::firstName($lead->learnerName()),
            'subject' => $session === null ? '' : new Localized((string) $session->name_ar, (string) $session->name_en),
            'teacher' => TrialReport::firstName((string) DB::table('users')->where('id', $trial->teacher_id)->value('name')),
            'link' => $this->links->report($trial, $this->family->locale($lead)),
            'offer' => $offer,
        ], ['screen' => 'family_trial', 'id' => $trial->session_id], 'trial-report:'.$trial->id.($again ? ':'.$now->format('YmdH') : ''));

        if ($trial->report_sent_at === null) {
            $trial->forceFill(['report_sent_at' => $now])->save();
        }
        LeadActivity::record($lead, $again ? 'report_resent' : 'report_sent', null, [], $actorId);

        return $sent > 0;
    }
}
