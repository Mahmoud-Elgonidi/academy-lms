<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Domain\NextTrial;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Support\NextTrials;
use Modules\Crm\Support\TrialFacts;
use Modules\People\Contracts\AgeGroup;
use Modules\Scheduling\Contracts\DTOs\TrialSlot;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\Site\Contracts\SiteApi;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One more free trial from an earlier one, booked by the family itself
 * (docs/architecture/02, section 27, J2), from its signed link (the report's,
 * or "book again" after a trial that did not take place) or its own account:
 *
 * - "second": the academy's guarantee after an assessed trial ("not the right
 *   teacher?"), with any teacher but the first one;
 * - "rebook": after the learner missed it, the teacher did, or the academy
 *   cancelled it (a teacher's absence or the academy's cancellation never
 *   counts against the family's free trial).
 *
 * Domain\NextTrialRules decides, asked again under the number's lock (the
 * same lock as J1's own booking), with J1's rules: one trial to come per
 * learner and subject, three per number, never for a learner who studies the
 * subject, only while families book their own trial. The times are J1's
 * (TrialBooking: the subject, the learner's age, the teacher they would
 * rather have, MinorsGuard, the academy's notice), on the family's clock. A
 * lost lead comes back; the new trial says which one it follows, so staff
 * see it on the lead. No code: the link went to the family's number, and an
 * account proves itself.
 */
final class BookNextTrial
{
    public function __construct(
        private readonly NextTrials $next,
        private readonly TrialFacts $facts,
        private readonly TrialBooking $trials,
        private readonly SiteApi $site,
    ) {}

    /**
     * @return list<TrialSlot>
     *
     * @throws ValidationException on "starts_at" with why it cannot be booked
     */
    public function slots(LeadTrial $trial, bool $familyLook, ?CarbonImmutable $from = null, ?string $timezone = null): array
    {
        $facts = $this->allowed($trial, $familyLook);

        return $this->trials->slots($this->search($trial, $facts, $from, $timezone));
    }

    /**
     * @return string the new lesson's id
     *
     * @throws ValidationException on "starts_at" with why it cannot be booked, or when the time was just taken
     */
    public function handle(LeadTrial $trial, CarbonImmutable $startsAt, bool $familyLook, ?string $actorId = null): string
    {
        $lead = $trial->lead;
        if ($lead?->phone_e164 === null) {
            throw new NotFoundHttpException;
        }

        return DB::transaction(function () use ($trial, $lead, $startsAt, $familyLook, $actorId) {
            $this->facts->lockNumber((string) $lead->phone_e164);
            $facts = $this->allowed($trial, $familyLook);
            if ($lead->stage === LeadStage::Lost) {
                // The family came back by itself: the lead is open again.
                $lead->forceFill(['lost_reason' => null])->save();
                $lead->moveTo(LeadStage::Contacted, null, log: false);
                LeadActivity::record($lead, 'reopened', null, [], null);
            }
            $sessionId = $this->trials->book($this->search($trial, $facts), $startsAt, (string) $lead->learner_id, $actorId ?? (string) $lead->contact_id);
            $session = DB::table('sessions')->where('id', $sessionId)->first(['teacher_id', 'starts_at']);
            LeadTrial::query()->updateOrCreate(['session_id' => $sessionId], [
                'lead_id' => $lead->id,
                'teacher_id' => $session->teacher_id,
                'starts_at' => CarbonImmutable::parse($session->starts_at)->utc(),
                'kind' => $facts->kind,
                'follows_trial_id' => $trial->id,
            ]);
            LeadActivity::record($lead, $facts->kind === 'second' ? 'second_trial' : 'rebooked', null, ['follows' => $trial->id], $actorId);

            return $sessionId;
        });
    }

    /** The facts, when the rules allow it; else why not. */
    private function allowed(LeadTrial $trial, bool $familyLook): NextTrial
    {
        $facts = $this->next->facts($trial, $familyLook) ?? throw new NotFoundHttpException;
        $why = NextTrialRules::whyNot($facts);
        if ($why !== null) {
            throw ValidationException::withMessages(['starts_at' => __('crm::report.next.why.'.$why, ['count' => NextTrialRules::MAX_OPEN_PER_NUMBER])]);
        }

        return $facts;
    }

    /** J1's search for the learner, on the family's clock; a second trial leaves the first teacher out. */
    private function search(LeadTrial $trial, NextTrial $facts, ?CarbonImmutable $from = null, ?string $timezone = null): TrialSlotSearch
    {
        $lead = $trial->lead;
        $year = CarbonImmutable::now()->year;
        $age = $lead->for_child && $lead->learner_birth_year !== null ? $year - $lead->learner_birth_year : null;

        return new TrialSlotSearch(
            subjectId: (string) DB::table('sessions')->where('id', $trial->session_id)->value('subject_id'),
            timezone: $timezone ?? $lead->timezone,
            ageGroup: AgeGroup::forAge($age)?->value,
            forMinor: $lead->for_child,
            teacherGender: $lead->teacher_gender,
            from: $from,
            noticeHours: $this->site->trialSettings()->noticeHours,
            excludeTeacherId: $facts->kind === 'second' ? $trial->teacher_id : null,
        );
    }
}
