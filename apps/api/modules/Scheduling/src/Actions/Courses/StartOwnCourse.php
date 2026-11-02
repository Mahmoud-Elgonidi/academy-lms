<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\Groups\JoinGroup;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\GroupJoin;
use Modules\Scheduling\Contracts\DTOs\OwnStart;
use Modules\Scheduling\Contracts\DTOs\OwnStarted;
use Modules\Scheduling\Contracts\StartOffers;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\Scheduling\Queries\Start\CourseTimes;
use Modules\Scheduling\Queries\Start\StartRules;
use Modules\Scheduling\Queries\Start\StartSlots;
use Modules\Scheduling\Support\StartRefused;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\PlanLimitReached;

/**
 * A family starts a course on its own and gets its invoice to pay (docs/
 * architecture/02, section 27, J3): the same enrolment the office makes from
 * Find a teacher (EnrollStudent: the course, its credit, the subscription
 * and the first block's invoice through WriteInvoice, the weekly times and
 * their lessons, waiting unpaid under rule 16), or a seat in a group
 * (JoinGroup), or a course joining the learner's shared package (no invoice
 * of its own). All or nothing, one at a time per learner (an advisory lock),
 * after these, each refused with a kind sentence saying what to do next
 * (StartRefused):
 *
 *  - the academy lets families start, and the viewer is in the classic look;
 *  - the plan is still offered, priced in the payer's region (Billing's one
 *    rule, as the trial's report: the region they pay in, else the payer's
 *    or the learner's country's, else the default),
 *    at the price they saw (else price_changed, with the new price);
 *  - a child waiting for a guardian's consent has it first (consent: the
 *    client shows the wording and agrees through POST family/consents/{id});
 *  - no course of the learner in the subject that has not ended;
 *  - the teacher still takes them (StartRules::teachers, MinorsGuard), the
 *    plan's number of weekly times, one a day, each still free in its first
 *    weeks but a skipped one (StartSlots: an academy holiday or the
 *    teacher's leave, at most WEEKS - MIN_WEEKS of them; else time_taken,
 *    with the fresh times), its lessons held and confirmed without the
 *    skipped weeks (PlaceBookingHold, EnrollStudent, which keeps them off
 *    the weekly time); when the skipped weeks would carry the first block's
 *    last lesson past the plan's validity, the course keeps the days
 *    (course_starts.extra_validity_days, given at the first payment);
 *  - a group with a seat for them, of the plan's shape (JoinGroup's lock and
 *    rules: seats, ages, minors with adults, level);
 *  - room in the academy's plan (CreateEnrollment: academy_full).
 *
 * The trial's offer (J2, StartOffers) goes on the first invoice by itself
 * while it is open and bigger than the family discount (never both); ended
 * meanwhile, the course starts at the plain price and the answer says so.
 *
 * The first invoice is due by the pay-by moment (BillingApi::setInvoiceDue):
 * the first lesson still payable before rule 16 cancels it unpaid, less
 * unpaid_cancel_hours; the answer's pay_by is the same moment. With no such
 * lesson (a forming group, rule 16 off, a shared package) the academy's
 * due_days stand. The offer's end only bounds when the course may start with
 * it: once on the invoice, the discount stays until it is paid or voided.
 */
final class StartOwnCourse
{
    public function __construct(
        private readonly StartRules $rules,
        private readonly StartSlots $slots,
        private readonly BillingApi $billing,
        private readonly PeopleApi $people,
        private readonly StartOffers $offers,
        private readonly PlaceBookingHold $hold,
        private readonly EnrollStudent $enrol,
        private readonly JoinGroup $join,
        private readonly CourseTimes $times,
    ) {}

    /** The plan as offered now, priced in the payer's region; refuses when it is not offered or has no price there. */
    public function priced(string $planId, string $payerId, string $learnerId): array
    {
        $plan = collect($this->billing->planOptions())->first(fn (PlanOption $p) => $p->id === $planId);
        if ($plan === null) {
            throw StartRefused::because('no_plan');
        }
        $region = $this->billing->priceRegionFor($payerId, $learnerId);
        $price = $region === null ? null : ($plan->prices[$region->id] ?? null);
        if ($price === null) {
            throw StartRefused::because('no_price');
        }

        return [$plan, $region, $price];
    }

    public function handle(OwnStart $start, ?CarbonImmutable $now = null): OwnStarted
    {
        $now ??= CarbonImmutable::now();
        $this->rules->assertCanStart($start->viewerId);
        /** @var PlanOption $plan @var RegionOption $region @var Money $price */
        [$plan, $region, $price] = $this->priced($start->planId, $start->viewerId, $start->learnerId);
        if ($price->minor !== $start->priceMinor || $price->currency !== strtoupper($start->currency)) {
            throw StartRefused::because('price_changed', ['price' => $price->format()], [
                'price' => ['amount_minor' => $price->minor, 'currency' => $price->currency, 'amount' => $price->format()],
            ], 409);
        }
        if ($this->people->consentState($start->learnerId)->waiting()) {
            $name = $this->rules->firstName((string) DB::table('users')->where('id', $start->learnerId)->value('name'));
            throw StartRefused::because('consent', ['learner' => $name], ['student_id' => $start->learnerId], 409);
        }
        if ($plan->group !== ($start->groupId !== null)) {
            throw StartRefused::because($plan->group ? 'group_needed' : 'times_needed');
        }

        return DB::transaction(function () use ($start, $plan, $region, $price, $now) {
            // One start at a time per learner: a double tap or a second device waits, then reads the course the first made.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['own-start:'.$start->learnerId]);
            $this->rules->assertNoCourse($start->learnerId, $start->subjectId);
            [$code, $offer, $percent] = $this->offer($start, $plan, $region);

            try {
                $result = $plan->group
                    ? $this->joinGroup($start, $plan, $region, $code, $now)
                    : $this->enrolPrivate($start, $plan, $region, $code, $now);
            } catch (PlanLimitReached) {
                throw StartRefused::because('academy_full');
            }
            [$enrollmentId, $invoiceId, $teacherId, $groupId, $extra] = $result;
            if ($result[5] ?? false) {
                [$offer, $percent] = ['ended', $percent];      // its moment passed while this ran: the plain price
            }
            $package = $plan->shared && $invoiceId === null;
            if ($package) {
                [$offer, $percent] = [$offer === 'none' ? 'none' : 'package', $percent];
            }

            CourseStart::query()->create([
                'enrollment_id' => $enrollmentId, 'learner_id' => $start->learnerId, 'started_by' => $start->viewerId, 'payer_id' => $start->viewerId,
                'plan_id' => $plan->id, 'teacher_id' => $teacherId, 'group_id' => $groupId, 'price_minor' => $price->minor, 'currency' => $price->currency,
                'offer_applied' => $offer === 'applied',
                // Days the first block lasts longer for its skipped weeks; none when it joined a package's balance.
                'extra_validity_days' => $invoiceId === null ? 0 : (int) ($extra['extra_days'] ?? 0),
            ]);
            app(AuditTrail::class)->record('enrollment.started_own', 'enrollment', $enrollmentId, array_filter([
                'plan' => $plan->name, 'price' => $price->format(), 'format' => $plan->group ? 'group' : 'private', 'offer' => $offer, 'package' => $package ?: null,
            ], fn ($v) => $v !== null));

            $first = $this->times->firstLesson($enrollmentId, $now);
            // The first invoice is due by the pay-by moment, the one the answer gives: the first lesson that can still be paid
            // for before rule 16 cancels it, less its hours (a running group's next lesson may already be too close to save).
            $unpaid = StartSlots::unpaidHours();
            $payable = $invoiceId !== null && $unpaid > 0 ? $this->times->firstLesson($enrollmentId, $now->addHours($unpaid)) : null;
            $payBy = $payable === null ? null : $payable['at']->subHours($unpaid);
            if ($payBy !== null) {
                $this->billing->setInvoiceDue((string) $invoiceId, $payBy);
            }

            return new OwnStarted(
                enrollmentId: $enrollmentId,
                invoiceId: $invoiceId,
                teacherId: $teacherId,
                weeklyTimes: $this->times->weekly($enrollmentId, $now),
                firstLesson: $first['at'] ?? null,
                lessons: (int) ($extra['lessons'] ?? 0),
                skippedDates: collect([...array_column($extra['weeks'] ?? [], 'date'), ...($extra['skipped'] ?? [])])->map(fn ($d) => (string) $d)->unique()->sort()->values()->all(),
                joinedPackage: $package,
                offer: $offer,
                offerPercentBp: $percent,
                groupId: $groupId,
                seat: $extra['seat'] ?? null,
                capacity: $extra['capacity'] ?? null,
                groupStarted: (bool) ($extra['started'] ?? false),
                payBy: $payBy,
                skippedWeeks: $extra['weeks'] ?? [],
            );
        });
    }

    /**
     * The trial's offer for this learner and subject, paid by this payer: its code when it goes on the first invoice
     * (open, this plan allowed, bigger than the family discount), and what became of it.
     *
     * @return array{0: ?string, 1: string, 2: ?int} [code, applied|ended|family_discount_bigger|not_for_plan|package|none, percent]
     */
    private function offer(OwnStart $start, PlanOption $plan, RegionOption $region): array
    {
        $found = $this->offers->forLearner($start->learnerId, $start->subjectId, $start->viewerId);
        $coupon = $found === null ? null : $this->billing->personalCoupon($found->couponId);
        if ($coupon === null || $coupon->used || ! $coupon->active || $coupon->payerId !== $start->viewerId) {
            return [null, 'none', null];
        }
        if (! $coupon->open()) {
            return [null, 'ended', $coupon->percentBp];
        }
        if ($plan->shared && $this->billing->sharedPackage($start->learnerId, $plan->id, $start->viewerId) !== null) {
            return [null, 'package', $coupon->percentBp];     // joining the package: no invoice of its own to take it
        }
        $quote = $this->billing->quote($plan->id, $region->id, $start->viewerId, $start->learnerId, $coupon->code);
        if ($quote === null || $quote->couponError !== null) {
            return [null, 'not_for_plan', $coupon->percentBp];
        }
        if ($quote->couponNote !== null) {
            return [null, 'family_discount_bigger', $coupon->percentBp];   // never both: the bigger applies, and the offer stays unused
        }

        return [$coupon->code, 'applied', $coupon->percentBp];
    }

    /** @return array{0: string, 1: ?string, 2: string, 3: null, 4: array<string, mixed>, 5: bool} */
    private function enrolPrivate(OwnStart $start, PlanOption $plan, RegionOption $region, ?string $code, CarbonImmutable $now): array
    {
        if ($start->teacherId === null) {
            throw StartRefused::because('times_needed');
        }
        $teacher = $this->rules->teachers($start->learnerId, $start->subjectId, [$start->teacherId])[$start->teacherId] ?? null;
        if ($teacher === null) {
            throw StartRefused::because('teacher_unavailable');
        }
        $keys = array_values(array_unique($start->times));
        $want = $plan->shared ? null : $plan->sessionsPerWeek;
        if ($keys === [] || count($keys) !== count($start->times) || ($want !== null && count($keys) !== $want) || ($want === null && count($keys) > max(1, $plan->sessionsPerWeek))) {
            throw StartRefused::because('times_count', ['count' => $want ?? $plan->sessionsPerWeek]);
        }

        $free = $this->slots->private($teacher, $start->learnerId, $plan, $start->timezone, $start->viewerId, $now);
        $taken = array_values(array_diff($keys, array_keys($free)));
        if ($taken !== []) {
            throw $this->taken($free, $plan, $start->timezone);
        }
        $chosen = array_map(fn (string $k) => $free[$k], $keys);
        if (count(array_unique(array_map(fn (Candidate $c) => $c->studentWeekday, $chosen))) !== count($chosen)) {
            throw StartRefused::because('times_count', ['count' => count($chosen)]);
        }

        // The lessons only: a skipped week (a holiday, the teacher's leave) is booked never, and kept off the weekly time.
        $occurrences = array_merge(...array_map(fn (Candidate $c) => $c->occurrences, $chosen));
        $weeks = array_merge(...array_map(fn (Candidate $c) => $c->skipped, $chosen));
        usort($weeks, fn (array $a, array $b) => $a['at'] <=> $b['at']);
        try {
            $batch = $this->hold->handle((string) $teacher->id, $start->learnerId, $occurrences, $plan->sessionMinutes, $start->viewerId);
        } catch (ValidationException) {
            throw $this->taken($this->slots->private($teacher, $start->learnerId, $plan, $start->timezone, $start->viewerId, $now), $plan, $start->timezone);
        }

        $terms = fn (?string $c) => new EnrollmentTerms($plan->id, $start->viewerId, $region->id, $c);
        $ended = false;
        try {
            $booked = $this->enrol->handle($batch, $start->learnerId, $start->subjectId, $terms($code), $start->viewerId, $weeks);
        } catch (PlanLimitReached $e) {
            throw $e;
        } catch (ValidationException $e) {
            if ($code === null || ! $e->validator->errors()->has('coupon')) {
                throw $e;
            }
            // The offer's moment passed between the quote and the invoice: the course starts at the plain price.
            $booked = $this->enrol->handle($batch, $start->learnerId, $start->subjectId, $terms(null), $start->viewerId, $weeks);
            $ended = true;
        }

        return [$booked->enrollmentId, $booked->invoice?->id, (string) $teacher->id, null, [
            'lessons' => $booked->sessions, 'skipped' => $booked->skippedDates, 'weeks' => $weeks,
            'extra_days' => StartSlots::extraDays($chosen, $plan->sessionsPerBlock, $plan->validityDays, $now),
        ], $ended];
    }

    /** @return array{0: string, 1: ?string, 2: string, 3: string, 4: array<string, mixed>, 5: bool} */
    private function joinGroup(OwnStart $start, PlanOption $plan, RegionOption $region, ?string $code, CarbonImmutable $now): array
    {
        // The group's own lock first (JoinGroup takes it again): the seat counted here is the one taken.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['group:'.$start->groupId]);
        $facts = $this->rules->learnerFacts($start->learnerId);
        $groups = collect($this->slots->groups($start->subjectId, $plan, $facts['age_group'], $facts['minor'], $start->timezone, null, $now))->keyBy('id');
        if (! $groups->has($start->groupId)) {
            $exists = DB::table('lesson_groups')->where('id', $start->groupId)->where('subject_id', $start->subjectId)->whereIn('status', ['forming', 'running'])->first(['capacity']);
            $full = $exists !== null && DB::table('group_members')->where('group_id', $start->groupId)
                ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $now->toDateString()))->count() >= (int) $exists->capacity;
            throw StartRefused::because($full ? 'group_full' : 'group_unavailable', [], ['groups' => $groups->keys()->values()->all()]);
        }

        $terms = fn (?string $c) => new GroupJoin($plan->id, $start->viewerId, $region->id, $c);
        $ended = false;
        try {
            $joined = $this->join->handle((string) $start->groupId, $start->learnerId, $terms($code), $start->viewerId);
        } catch (PlanLimitReached $e) {
            throw $e;
        } catch (ValidationException $e) {
            if ($code === null || ! $e->validator->errors()->has('coupon')) {
                throw $e;
            }
            $joined = $this->join->handle((string) $start->groupId, $start->learnerId, $terms(null), $start->viewerId);
            $ended = true;
        }

        return [$joined->enrollmentId, $joined->invoice->id, (string) $groups[$start->groupId]['teacher_id'], (string) $start->groupId,
            ['lessons' => $joined->lessons, 'seat' => $joined->seat, 'capacity' => $joined->capacity, 'started' => $joined->started], $ended];
    }

    /** A time went meanwhile: refused with the times free now, so the family chooses again at once. */
    private function taken(array $free, PlanOption $plan, string $timezone): StartRefused
    {
        $times = [];
        foreach ($free as $key => $c) {
            $times[] = ['key' => (string) $key, 'weekday' => $c->studentWeekday, 'start' => $c->studentTime, 'end' => $c->studentEnd($plan->sessionMinutes)];
        }

        return StartRefused::because('time_taken', [], ['times' => $times, 'timezone' => $timezone], 409);
    }
}
