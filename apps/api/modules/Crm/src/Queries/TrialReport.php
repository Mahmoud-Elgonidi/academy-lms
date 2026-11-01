<?php

namespace Modules\Crm\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Support\NextTrials;
use Modules\Crm\Support\TrialFacts;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\OwnCourseStart;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * The family's report after a free trial (docs/architecture/02, section 27,
 * J2), read each time from the trial, its assessment and today's prices:
 * the learner's first name, the subject, when it was, the teacher's first
 * name (and their card, when it is on the academy's page), and what the
 * teacher wrote for the family (level, strengths, what to work on, their
 * words); the plan and lessons a week they recommend with its price in the
 * family's price region (Billing's: the region they pay in, else their
 * country's, else the default); the academy's offer while it is open, with
 * its end and the price after it (the bigger of the offer and the family
 * discount applies, as on the invoice); a course the learner already has in
 * the subject (then no offer); and the second trial with another teacher,
 * while it may be booked. Never the teacher's notes for the academy, sales'
 * notes, KPIs or pay. Money, the offer and booking are for the classic look
 * alone (a guardian or an adult): a child's own screen reads the rest.
 *
 * A report exists only for a trial that took place (attended, its lesson
 * completed) and was assessed, while the family's data is there (not erased).
 */
final class TrialReport
{
    public function __construct(
        private readonly BillingApi $billing,
        private readonly PeopleApi $people,
        private readonly TrialFacts $facts,
        private readonly NextTrials $next,
        private readonly LocalTimePresenter $time,
        private readonly OwnCourseStart $start,
    ) {}

    public static function ready(LeadTrial $trial): bool
    {
        // An erased family's assessment is blank (Privacy): no report is left to read.
        return $trial->status === 'attended' && $trial->isAssessed() && $trial->level !== null
            && DB::table('sessions')->where('id', $trial->session_id)->value('status') === 'completed';
    }

    /**
     * @param  bool  $classic  the classic look: prices, the offer and the second trial
     * @param  ?string  $viewerId  who reads it (signed in); the family's contact through its link
     * @return array<string, mixed>
     */
    public function present(LeadTrial $trial, bool $classic, ?string $timezone = null, ?CarbonImmutable $now = null, ?string $viewerId = null): array
    {
        $now ??= CarbonImmutable::now();
        $lead = $trial->lead;
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $session = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->where('s.id', $trial->session_id)->first(['s.subject_id', 'su.name_ar', 'su.name_en']);
        $subjectId = (string) ($session->subject_id ?? '');
        $teacher = (string) DB::table('users')->where('id', $trial->teacher_id)->value('name');
        $card = $this->people->publicTeacherOf($trial->teacher_id);
        $course = $lead->learner_id !== null && $subjectId !== '' ? $this->facts->course($lead->learner_id, $subjectId) : null;
        $plans = $trial->recommended_plan_id !== null ? collect($this->billing->planOptions())->keyBy('id') : collect();
        $plan = $trial->recommended_plan_id !== null ? $plans->get($trial->recommended_plan_id) : null;
        $payer = (string) ($lead->contact_id ?? $lead->learner_id);
        $region = $classic ? $this->billing->priceRegionFor($lead->contact_id, $lead->country) : null;
        $price = $plan !== null && $region !== null && isset($plan->prices[$region->id]) ? $plan->prices[$region->id] : null;

        $offerState = $classic ? $this->offerState($trial, $course !== null, $now) : 'none';
        $offer = $offerState === 'open' ? $this->openOffer($trial, $plan, $region?->id, $payer, (string) $lead->learner_id, $timezone) : null;
        $withdrawn = $trial->recommended_plan_id !== null && $plan === null;

        return [
            /** The trial lesson's id (the family's trials are named by their lesson). */
            'id' => $trial->session_id,
            /** The learner's first name. */
            'learner' => self::firstName($lead->learnerName()),
            'subject' => ['id' => $subjectId, 'name' => (string) ($locale === 'en' ? ($session->name_en ?? '') : ($session->name_ar ?? ''))],
            'held_at' => $this->time->present($trial->starts_at, $timezone),
            /** This was the free second trial, with another teacher. */
            'second' => $trial->kind === 'second',
            'teacher' => [
                /** The teacher's first name, with a title before it ("أ. منى"). */
                'name' => self::firstName($teacher),
                /** Their card on the academy's page, when it shows one: the name they chose, a few words, a photo. */
                'card' => $card === null ? null : ['name' => $card->displayName, 'bio' => $card->bio, 'photo_url' => $card->photoUrl],
            ],
            /** What the teacher found, in their words for the family. */
            'level' => (string) $trial->level,
            'strengths' => $trial->strengths,
            'to_work_on' => $trial->to_work_on,
            /** The teacher's words to the family. */
            'note' => $trial->family_note,
            'recommendation' => [
                /** The plan the teacher recommends; null when none, or the academy no longer offers it (plan_withdrawn). */
                'plan' => $plan === null ? null : self::plan($plan),
                'plan_withdrawn' => (bool) $withdrawn,
                'per_week' => $trial->recommended_per_week,
                /** The plan's block price in the family's region, today (classic look only; null when unknown). */
                'price' => $price === null ? null : self::money($price, $locale),
                'region' => $region === null ? null : ['id' => $region->id, 'name' => $region->name, 'currency' => $region->currency],
            ],
            /** The learner's course in this subject that has not ended (pending_payment, active, paused): they have started, so no offer. */
            'course' => $course === null ? null : ['status' => $course->value],
            /** The academy's offer: open, ended (its time passed), used, started (a course began) or none (classic look only). */
            'offer_state' => $offerState,
            'offer' => $offer,
            /** The free second trial with another teacher, when the academy gives one (classic look only). */
            'next_trial' => $classic ? $this->secondTrial($trial, $timezone, $now) : null,
            /**
             * «ابدأ بهذه الباقة» (J3, classic look only): whether the family may start the course on its own now, and what to open
             * the start flow with (GET family/start/options, then slots and POST family/courses): the recommended plan while it is
             * offered, the trial's teacher while they still take the learner, the subject and the learner.
             */
            'start' => $classic && $lead->learner_id !== null && $subjectId !== '' ? $this->start($trial, $lead->learner_id, $subjectId, $plan, $viewerId ?? $lead->contact_id ?? $lead->learner_id) : null,
        ];
    }

    /** open, ended (its time passed, or turned off), used, started (a course began) or none (no offer was made). */
    private function offerState(LeadTrial $trial, bool $started, CarbonImmutable $now): string
    {
        if ($trial->offer_coupon_id === null) {
            return 'none';
        }
        if ($started) {
            return 'started';
        }
        $coupon = $this->billing->personalCoupon($trial->offer_coupon_id);

        return match (true) {
            $coupon === null => 'ended',
            $coupon->used => 'used',
            $coupon->open($now) => 'open',
            default => 'ended',
        };
    }

    /** @return ?array{percent: float|int, ends_at: ?array<string, string>, price: ?array<string, mixed>, saving: ?array<string, mixed>, family_discount_bigger: bool} */
    private function openOffer(LeadTrial $trial, ?PlanOption $plan, ?string $regionId, string $payer, string $learner, ?string $timezone): ?array
    {
        $coupon = $this->billing->personalCoupon((string) $trial->offer_coupon_id);
        if ($coupon === null) {
            return null;
        }
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $with = $without = null;
        if ($plan !== null && $regionId !== null) {
            $with = $this->billing->quote($plan->id, $regionId, $payer, $learner, $coupon->code);
            $without = $this->billing->quote($plan->id, $regionId, $payer, $learner);
            if ($with?->couponError !== null) {
                $with = $without = null;     // the coupon open but refused for this plan: the offer stands, its price is not shown
            }
        }
        $saving = $with !== null && $without !== null ? $without->total->minor - $with->total->minor : null;

        return [
            /** What the offer takes off, as promised when it was made. */
            'percent' => $coupon->percentBp / 100,
            /** When it ends: the report shows the time left. */
            'ends_at' => $this->time->present($coupon->expiresAt, $timezone),
            /** The recommended plan's first block with the offer (and the family discount, whichever is bigger); null without a plan or a price. */
            'price' => $with === null ? null : self::money($with->total, $locale),
            /** How much less than without the offer. */
            'saving' => $saving === null || $with === null ? null : self::money(Money::of($saving, $with->total->currency), $locale),
            /** The family discount is bigger than the offer, so it applies instead (they never add up). */
            'family_discount_bigger' => $saving === 0 && $without !== null && self::discounted($without),
        ];
    }

    /** @return array{available: bool, why_not: ?string, reason: ?string, learner_id: string, subject_id: string, plan_id: ?string, teacher_id: ?string, enrollment_id: ?string} */
    private function start(LeadTrial $trial, string $learnerId, string $subjectId, ?PlanOption $plan, string $viewerId): array
    {
        $check = $this->start->check($viewerId, $learnerId, $subjectId, $trial->teacher_id);

        return [
            /** Whether the family may start the course on its own now. */
            'available' => $check->available,
            /** Why not, in the family's words (what to do instead): the academy enrols itself, a course already began, ... */
            'why_not' => $check->whyNot,
            /** self_serve_off, has_course, course_pending_own, course_pending, course_paused, course_ending (null when available). */
            'reason' => $check->reason,
            'learner_id' => $learnerId,
            'subject_id' => $subjectId,
            /** The recommended plan, while the academy offers it. */
            'plan_id' => $plan?->id,
            /** The trial's teacher, while they still take the learner (else the family chooses another). */
            'teacher_id' => $check->teacherId,
            /** The learner's course in the subject, when that is why not. */
            'enrollment_id' => $check->enrollmentId,
        ];
    }

    /** @return ?array{kind: string, available: bool, why_not: ?string, until: ?array<string, string>} */
    private function secondTrial(LeadTrial $trial, ?string $timezone, CarbonImmutable $now): ?array
    {
        $facts = $this->next->facts($trial, true, $now);
        if ($facts === null || $facts->kind !== 'second' || ! NextTrialRules::shown($facts)) {
            return null;
        }
        $why = NextTrialRules::whyNot($facts);

        return [
            'kind' => 'second',
            /** Whether it may be booked now. */
            'available' => $why === null,
            /** Why not, in the family's words (what to do instead); null when it may be booked. */
            'why_not' => $why === null ? null : (string) __('crm::report.next.why.'.$why, ['count' => NextTrialRules::MAX_OPEN_PER_NUMBER]),
            /** The last moment to book it (SECOND_WITHIN_DAYS after the first trial). */
            'until' => $this->time->present($trial->starts_at->addDays(NextTrialRules::SECOND_WITHIN_DAYS), $timezone),
        ];
    }

    private static function discounted(PriceQuote $quote): bool
    {
        return $quote->discounts !== [];
    }

    /** @return array<string, mixed> */
    private static function plan(PlanOption $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            /** private (one student a lesson) or group. */
            'format' => $plan->group ? 'group' : 'private',
            'session_minutes' => $plan->sessionMinutes,
            'sessions_per_week' => $plan->sessionsPerWeek,
            'sessions_per_block' => $plan->sessionsPerBlock,
            'validity_days' => $plan->validityDays,
        ];
    }

    /** @return array{price: string, amount_minor: int, currency: string} */
    private static function money(Money $money, string $locale): array
    {
        return ['price' => $money->format($locale), 'amount_minor' => $money->minor, 'currency' => $money->currency];
    }

    /** A first name as families read it: with a title before it ("أ. منى", "Dr. Emiliano"). */
    public static function firstName(string $name): string
    {
        return preg_match('/^(?:\S+\.\s+)?\S+/u', trim($name), $first) === 1 ? $first[0] : '';
    }
}
