<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Domain\TrialOfferRules;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialReport;
use Modules\Crm\Support\FamilyContact;
use Modules\Crm\Support\ReportLinks;
use Modules\Crm\Support\TrialFacts;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Contracts\SiteApi;

/**
 * Every hour (crm:follow-up): a new lead nobody has talked to once the
 * academy's promised hours (site_settings.response_hours) have passed, and
 * a trial that took place a day ago with no assessment yet, each told once
 * to whoever follows the lead (its owner, else everyone who works leads),
 * who can ask the teacher. A lead older than a week is left to the board.
 * And the family's offer after its trial (J2), once, in its last day
 * (Domain\TrialOfferRules::remindDue): never once it was used, the learner
 * started a course in the subject, or it ended.
 */
final class FollowUpLeads
{
    public const LOOK_BACK_DAYS = 7;

    public const ASSESS_WITHIN_HOURS = 24;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly SiteApi $site,
        private readonly BillingApi $billing,
        private readonly TrialFacts $facts,
        private readonly FamilyContact $family,
        private readonly ReportLinks $links,
    ) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $hours = $this->site->trialSettings()->responseHours;
        $sent = 0;
        $managers = null;
        $sales = function (?string $ownerId) use (&$managers): array {
            return $ownerId !== null ? [$ownerId] : ($managers ??= $this->identity->usersWithAbility(Ability::ManageLeads));
        };

        $waiting = Lead::query()->where('stage', LeadStage::New->value)->whereNull('last_contact_at')
            ->where('created_at', '<=', $now->subHours($hours))->where('created_at', '>', $now->subDays(self::LOOK_BACK_DAYS))
            ->orderBy('created_at')->get();
        foreach ($waiting as $lead) {
            $sent += $this->notifications->send(new Message('crm.lead_waiting', $sales($lead->owner_id), [
                'name' => $lead->contact_name,
                'hours' => (string) $hours,
            ], ['screen' => 'lead', 'id' => $lead->id], "lead-waiting:{$lead->id}"));
        }

        $unassessed = LeadTrial::query()->with('lead')->where('status', 'attended')->whereNull('assessed_at')
            ->where('starts_at', '<=', $now->subHours(self::ASSESS_WITHIN_HOURS))->where('starts_at', '>', $now->subDays(self::LOOK_BACK_DAYS))
            ->orderBy('starts_at')->get();
        foreach ($unassessed as $trial) {
            $teacher = (string) DB::table('users')->where('id', $trial->teacher_id)->value('name');
            $sent += $this->notifications->send(new Message('crm.trial_unassessed', $sales($trial->lead->owner_id), [
                'name' => $trial->lead->learnerName(),
                'teacher' => $teacher,
            ], ['screen' => 'trial', 'id' => $trial->id], "trial-unassessed:{$trial->id}"));
        }

        return $sent + $this->offersEnding($now);
    }

    /** The family's offer in its last day: one reminder, while it may still be used. */
    private function offersEnding(CarbonImmutable $now): int
    {
        $sent = 0;
        $ending = LeadTrial::query()->with('lead')->whereNotNull('offer_coupon_id')->whereNull('offer_reminded_at')->whereNotNull('report_sent_at')
            ->where('offer_ends_at', '>', $now)->where('offer_ends_at', '<=', $now->addHours(TrialOfferRules::REMIND_BEFORE_HOURS))
            ->orderBy('offer_ends_at')->get();
        foreach ($ending as $trial) {
            /** @var LeadTrial $trial */
            $lead = $trial->lead;
            $subjectId = (string) DB::table('sessions')->where('id', $trial->session_id)->value('subject_id');
            if (! TrialOfferRules::remindDue($trial->report_sent_at, $trial->offer_ends_at, $now)
                || $this->billing->personalCoupon((string) $trial->offer_coupon_id)?->open($now) !== true
                || ($lead->learner_id !== null && $this->facts->studying($lead->learner_id, $subjectId))) {
                continue;
            }
            $sent += $this->family->send($lead, 'crm.trial_offer_ending', [
                'student' => TrialReport::firstName($lead->learnerName()),
                'percent' => (string) ((int) $trial->offer_percent_bp / 100),
                'until' => $trial->offer_ends_at,
                'link' => $this->links->report($trial, $this->family->locale($lead)),
            ], ['screen' => 'family_trial', 'id' => $trial->session_id], "trial-offer-ending:{$trial->id}");
            $trial->forceFill(['offer_reminded_at' => $now])->save();
        }

        return $sent;
    }
}
