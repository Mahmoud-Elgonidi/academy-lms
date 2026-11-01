<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Support\TrialFacts;
use Modules\Crm\Support\WebsiteLeads;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\AgeGroup;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Contracts\DTOs\JoinRequestData;
use Modules\Site\Contracts\SiteApi;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A family books its own free trial from the academy's page (docs/
 * architecture/02, section 27), while the academy lets families book
 * (site_settings.trial_booking = family): with the code sent to its number
 * (withCode: the request is kept as the page's form keeps it), or from the
 * "pick a time" link the instant reply sent to that number (fromPick). Then,
 * under a lock on the number: one open trial per learner and subject, and
 * MAX_OPEN_PER_NUMBER at most per number; the lead (the request's, the open
 * one with that number for that learner, or a new one handed out in turn);
 * the learner's account (PrepareLearner: the guardian and a child under them,
 * no consent asked yet; a number that is someone else's way in is refused
 * without saying whose); never for a learner who already studies the subject
 * or had their free trial in it; and the lesson itself through
 * Scheduling's TrialBooking (the least busy teacher free then who takes
 * trials, teaches the subject and the age, of the gender asked, cleared for
 * a child). Scheduling tells the family (the time, the class link, change or
 * cancel) and the teacher; the CRM ties the trial to the lead.
 */
final class BookOwnTrial
{
    /** Open trials one number may hold at once (a parent with two children, or two subjects). */
    public const MAX_OPEN_PER_NUMBER = 3;

    public function __construct(
        private readonly SiteApi $site,
        private readonly IdentityApi $identity,
        private readonly WebsiteLeads $leads,
        private readonly PrepareLearner $prepare,
        private readonly TrialBooking $trials,
        private readonly TenantContext $tenants,
        private readonly TrialFacts $facts,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the request as the page's form sends it (phone, name, subject_id, for_child, ...)
     * @return string the lesson's id
     *
     * @throws ValidationException on "code" when the code is wrong, or why it cannot be booked
     */
    public function withCode(array $data, string $challengeId, string $code, CarbonImmutable $startsAt, string $locale): string
    {
        $this->ensureOpen();
        // Checked first and on its own: a wrong code stays counted whatever comes next.
        $this->identity->verifyPhoneCode($challengeId, $code, (string) ($data['phone'] ?? ''));

        // The request and the lesson together: a time taken meanwhile keeps nothing, and the family picks another.
        return DB::transaction(fn () => $this->book($this->site->recordTrialRequest($data, $locale), $startsAt));
    }

    /**
     * @return string the lesson's id
     *
     * @throws NotFoundHttpException the link names no request waiting for a time
     */
    public function fromPick(string $token, CarbonImmutable $startsAt): string
    {
        $this->ensureOpen();
        $request = $this->site->requestForPick($token) ?? throw new NotFoundHttpException;

        return $this->book($request->id, $startsAt);
    }

    /** The search for a request's trial: its subject, the learner's age, the teacher they would rather have, on their clock. */
    public function search(JoinRequestData $request, ?CarbonImmutable $from = null): TrialSlotSearch
    {
        return new TrialSlotSearch(
            subjectId: (string) $request->subjectId,
            timezone: $request->timezone ?? $this->tenants->require()->defaultTimezone,
            ageGroup: $request->forChild && $request->childBirthYear !== null ? AgeGroup::forAge(CarbonImmutable::now()->year - $request->childBirthYear)?->value : null,
            forMinor: $request->forChild,
            teacherGender: $request->teacherGender,
            from: $from,
            noticeHours: $this->site->trialSettings()->noticeHours,
        );
    }

    private function ensureOpen(): void
    {
        if (! $this->site->trialSettings()->familyBooks) {
            throw ValidationException::withMessages(['starts_at' => __('crm::leads.errors.booking_off')]);
        }
    }

    private function book(string $requestId, CarbonImmutable $startsAt): string
    {
        $request = $this->site->joinRequest($requestId) ?? throw new NotFoundHttpException;
        if ($request->subjectId === null) {
            throw ValidationException::withMessages(['subject_id' => __('crm::leads.errors.no_subject')]);
        }
        $refuse = fn (string $why, array $replace = []) => throw ValidationException::withMessages(['starts_at' => __('crm::leads.errors.'.$why, $replace)]);

        return DB::transaction(function () use ($request, $startsAt, $refuse) {
            $this->facts->lockNumber($request->phone);
            $open = $this->facts->openOnNumber($request->phone);
            // One open trial per learner and subject; a few at most per number (two children, two subjects; never a flood).
            foreach ($open as $trial) {
                $sameLearner = (bool) $trial->for_child === $request->forChild
                    && (! $request->forChild || mb_strtolower(trim((string) $trial->learner_name)) === mb_strtolower(trim((string) $request->childName)));
                if ($sameLearner && $trial->subject_id === $request->subjectId) {
                    $refuse('trial_open');
                }
            }
            if ($open->count() >= self::MAX_OPEN_PER_NUMBER) {
                $refuse('too_many_open', ['count' => self::MAX_OPEN_PER_NUMBER]);
            }

            $lead = $this->leads->leadFor($request);
            $this->fitLearner($lead, $request);
            try {
                $learnerId = $this->prepare->handle($lead, null);
            } catch (ValidationException) {
                // The number is someone else's way in here (a teacher, a student for themself): never say whose.
                $refuse('number_in_use');
            }
            // A learner who already studies the subject, or had their free trial in it, is the academy's to talk to.
            if ($this->facts->studying($learnerId, $request->subjectId)) {
                $refuse('already_student');
            }
            if ($this->facts->hadTrial($learnerId, $request->subjectId)) {
                $refuse('had_trial');
            }

            $sessionId = $this->trials->book($this->search($request), $startsAt, $learnerId, (string) $lead->contact_id);
            LeadActivity::record($lead, 'booked_online', null, ['request' => $request->id], null);
            $this->site->trialBooked($request->id, $sessionId);

            return $sessionId;
        });
    }

    /**
     * The lead's learner is the request's: a lead with no account yet takes
     * the request's learner and wishes; one with a learner already must be
     * asking for the same person (a sibling is booked by the academy).
     */
    private function fitLearner(Lead $lead, JoinRequestData $request): void
    {
        if ($lead->learner_id === null) {
            $lead->forceFill(array_filter([
                'for_child' => $request->forChild,
                'learner_name' => $request->forChild ? $request->childName : null,
                'learner_birth_year' => $request->forChild ? $request->childBirthYear : null,
                'subject_id' => $request->subjectId,
                'teacher_gender' => $request->teacherGender ?? $lead->teacher_gender,
            ], fn ($v) => $v !== null) + ['for_child' => $request->forChild])->save();

            return;
        }
        $same = $lead->for_child === $request->forChild
            && (! $request->forChild || mb_strtolower(trim((string) $lead->learner_name)) === mb_strtolower(trim((string) $request->childName)));
        if (! $same) {
            throw ValidationException::withMessages(['starts_at' => __('crm::leads.errors.other_learner')]);
        }
    }
}
