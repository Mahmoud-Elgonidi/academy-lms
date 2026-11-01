<?php

namespace Modules\Crm\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Domain\NextTrial;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\LeadTrial;
use Modules\Site\Contracts\SiteApi;

/**
 * Gathers the facts for one more free trial from an earlier one (Domain\
 * NextTrialRules decides): which kind it would be, and the learner's and the
 * number's history. Read each time; BookNextTrial reads them again under the
 * number's lock.
 */
final class NextTrials
{
    public function __construct(private readonly TrialFacts $facts, private readonly SiteApi $site) {}

    /**
     * rebook: the trial did not take place (missed, or cancelled by the academy); second: it did, and was assessed.
     * Null when no trial follows from it (still to come, or cancelled by the family, who asks again on the page).
     */
    public static function kindAfter(LeadTrial $trial): ?string
    {
        return match (true) {
            $trial->kind === 'second' => null,     // the guarantee's own trial leads to no third (rebooking it is the academy's)
            $trial->status === 'attended' => 'second',
            $trial->status === 'missed', $trial->status === 'cancelled' && $trial->outcome === 'academy' => 'rebook',
            default => null,
        };
    }

    public function facts(LeadTrial $trial, bool $familyLook, ?CarbonImmutable $now = null): ?NextTrial
    {
        $kind = self::kindAfter($trial);
        $lead = $trial->lead;
        $subjectId = (string) DB::table('sessions')->where('id', $trial->session_id)->value('subject_id');
        if ($kind === null || $lead === null || $lead->learner_id === null || $subjectId === '') {
            return null;
        }
        $learner = $lead->learner_id;
        $phone = $lead->phone_e164;
        $open = $phone === null ? collect() : $this->facts->openOnNumber($phone);
        $settings = $this->site->trialSettings();

        return new NextTrial(
            kind: $kind,
            familyLook: $familyLook,
            familyBooks: $settings->familyBooks,
            secondTrialOn: CrmSettings::current()->second_trial,
            assessed: $trial->isAssessed() && $trial->status === 'attended',
            startedAt: $trial->starts_at,
            now: $now ?? CarbonImmutable::now(),
            studying: $this->facts->studying($learner, $subjectId),
            openTrial: $open->contains(fn (object $o) => $o->subject_id === $subjectId && $o->learner_id === $learner),
            openForNumber: $open->count(),
            secondUsed: $this->facts->secondUsed($learner, $subjectId),
            studentMissed: $this->facts->studentMissed($learner, $subjectId),
            hadTrial: $this->facts->hadTrial($learner, $subjectId),
            hasNumber: $phone !== null,
        );
    }
}
