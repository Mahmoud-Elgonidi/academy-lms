<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Courses\CancelOwnStart;
use Modules\Scheduling\Actions\Courses\StartOwnCourse;
use Modules\Scheduling\Contracts\DTOs\OwnStart;
use Modules\Scheduling\Contracts\StartOffers;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Queries\Start\StartRules;
use Modules\Scheduling\Queries\Start\StartSlots;
use Modules\Scheduling\Support\StartRefused;
use Modules\SharedKernel\Http\ProblemException;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The family starts a course and pays on its own (docs/architecture/02,
 * section 27, J3), for the portal and the app: what it may start (its
 * learners, the subjects, the plans priced for it, the teachers, the trial's
 * offer), a teacher's weekly times or the open groups, starting it (the
 * course, its lessons and its invoice with the pay link), and calling it off
 * before paying. In the classic look only (a guardian, or an adult for
 * themselves): a child's own screen never sees money. Every refusal is a
 * problem (start-refused, or guardian-consent-needed) with a reason the
 * clients act on and a sentence saying what to do next.
 */
final class FamilyStartController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly StartRules $rules,
        private readonly BillingApi $billing,
        private readonly LocalTimePresenter $time,
        private readonly ViewerTimezone $viewer,
    ) {}

    /**
     * What a family may start.
     *
     * The viewer's learners (with a child still waiting for a guardian's consent marked), the subjects the academy teaches
     * (each with the learner's course in it, if any, and why a new one cannot start), the plans priced in the payer's region
     * (private and group, their shape, the block's and a lesson's price, and what the first invoice would come to for this
     * learner: the sibling discount or the trial's offer, the bigger), and, for a subject, the teachers who take the learner
     * (the trial's teacher first) and the trial's offer. self_serve false: the academy enrols itself (why_off says how to
     * reach it). 403 start-refused (family_only) outside the classic look.
     */
    public function options(Request $request, CatalogApi $catalog, PeopleApi $people, StartOffers $offers, StartSlots $slots): JsonResponse
    {
        $valid = $request->validate(['learner_id' => ['nullable', 'string', 'max:64'], 'subject_id' => ['nullable', 'uuid']]);
        $me = $this->me($request);
        $learner = $this->family->learner($me, $valid['learner_id'] ?? null) ?? throw new NotFoundHttpException;
        $whyOff = null;
        try {
            $this->rules->assertCanStart($me);
        } catch (StartRefused $e) {
            if ($e->reason !== 'self_serve_off') {
                throw $this->problem($e);
            }
            $whyOff = (string) $e->validator->errors()->first('start');
        }
        $on = $whyOff === null;

        $learners = [];
        foreach ($on ? $this->family->learners($me) : [] as $l) {
            $learners[] = [
                'id' => $l->id,
                'name' => $l->name,
                /** The viewer themselves (an adult studying for themselves). */
                'is_self' => (bool) $l->isSelf,
                /** A child whose account waits for a guardian's consent: agree first (GET/POST family/consents), then start. */
                'consent_waiting' => $people->consentState($l->id)->waiting(),
            ];
        }

        $taught = $on ? $this->taughtSubjects() : [];
        $subjects = [];
        foreach ($catalog->subjectNames() as $id => $name) {
            if (! isset($taught[(string) $id])) {
                continue;
            }
            $course = $this->rules->courseIn($learner->id, (string) $id);
            $why = null;
            try {
                $this->rules->assertNoCourse($learner->id, (string) $id);
            } catch (StartRefused $e) {
                $why = (string) $e->validator->errors()->first('start');
            }
            $subjects[] = [
                'id' => (string) $id,
                'name' => (string) $name,
                /** The learner's course in it that has not ended: pending_payment, active or paused. */
                'course' => $course === null ? null : ['enrollment_id' => $course->id, 'status' => $course->status->value],
                'can_start' => $why === null,
                /** Why not, in the family's words (what to do instead). */
                'why_not' => $why === null ? null : (string) $why,
            ];
        }

        $region = $on ? $this->billing->priceRegionFor($me, $learner->id) : null;
        $subjectId = $on ? ($valid['subject_id'] ?? null) : null;
        $offer = null;
        $offerCode = null;
        if ($subjectId !== null) {
            $found = $offers->forLearner($learner->id, $subjectId, $me);
            $coupon = $found === null ? null : $this->billing->personalCoupon($found->couponId);
            if ($coupon !== null && $coupon->payerId === $me && ! $coupon->used && $coupon->active) {
                $open = $coupon->open();
                $offerCode = $open ? $coupon->code : null;
                $offer = [
                    /** open (applied by itself to the first invoice when it is bigger than the sibling discount) or ended. */
                    'state' => $open ? 'open' : 'ended',
                    'percent' => $coupon->percentBp / 100,
                    'ends_at' => $this->time->present($coupon->expiresAt),
                ];
            }
        }

        $plans = [];
        if ($region !== null) {
            foreach ($this->billing->planOptions() as $plan) {
                if (! isset($plan->prices[$region->id])) {
                    continue;
                }
                $plans[] = $this->plan($plan, $plan->prices[$region->id], $region->id, $me, $learner->id, $offerCode);
            }
        }

        $teachers = [];
        $trial = null;
        if ($subjectId !== null) {
            $trialRow = DB::table('sessions as s')->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id'))
                ->where('p.user_id', $learner->id)->where('p.role', 'student')->where('s.type', 'trial')->where('s.subject_id', $subjectId)->where('s.status', 'completed')
                ->orderByDesc('s.starts_at')->first(['s.id', 's.teacher_id']);
            $private = $this->rules->teachers($learner->id, $subjectId);
            $facts = $this->rules->learnerFacts($learner->id);
            $groupTeachers = [];
            foreach ($this->billing->planOptions(null, 'group') as $plan) {
                foreach ($slots->groups($subjectId, $plan, $facts['age_group'], $facts['minor'], $this->viewer->effective()) as $g) {
                    $groupTeachers[$g['teacher_id']][$g['id']] = true;
                }
            }
            $ids = array_values(array_unique([...array_keys($private), ...array_keys($groupTeachers)]));
            $trialTeacher = $trialRow !== null && in_array((string) $trialRow->teacher_id, $ids, true) ? (string) $trialRow->teacher_id : null;
            usort($ids, fn (string $a, string $b) => [$a !== $trialTeacher, array_search($a, array_keys($private), true) === false] <=> [$b !== $trialTeacher, array_search($b, array_keys($private), true) === false]);
            foreach ($ids as $id) {
                $card = $people->publicTeacherOf($id);
                $teachers[] = [
                    'id' => $id,
                    /** The name families read: the one on their card on the academy's page, else their first name with its title. */
                    'name' => $this->rules->teacherName($id, isset($private[$id]) ? $private[$id]->name : null),
                    'bio' => $card?->bio,
                    'photo_url' => $card?->photoUrl,
                    /** female | male | null */
                    'gender' => isset($private[$id]) ? $private[$id]->gender : null,
                    /** The teacher of the learner's free trial in this subject. */
                    'trial_teacher' => $id === $trialTeacher,
                    /** Takes new students for private lessons (GET family/start/slots for their weekly times). */
                    'private' => isset($private[$id]),
                    /** Open groups of theirs with a seat for the learner. */
                    'groups' => count($groupTeachers[$id] ?? []),
                ];
            }
            $trial = $trialRow === null ? null : [
                'session_id' => (string) $trialRow->id,
                /** Whether the trial's teacher still takes the learner (else choose another). */
                'teacher_available' => $trialTeacher !== null,
            ];
        }

        $unpaid = StartSlots::unpaidHours();

        return response()->json(['data' => [
            /** Whether families start courses on their own here. */
            'self_serve' => $on,
            /** Why not and how to reach the academy, when the academy enrols itself (then the lists are empty). */
            'why_off' => $whyOff,
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            'learners' => $learners,
            'subjects' => $subjects,
            /** Prices are in this region's currency (the region the payer pays in, else their learner's country's, else the default). */
            'region' => $region === null ? null : ['id' => $region->id, 'name' => $region->name, 'currency' => $region->currency],
            'plans' => $plans,
            'teachers' => $teachers,
            /** The learner's offer after a free trial in this subject (made to this payer), while it is not used. */
            'offer' => $offer,
            /** The learner's free trial in this subject, if they had one. */
            'trial' => $trial,
            'payment' => ! $on ? null : [
                /** Rule 16: a lesson still unpaid this many hours before it starts is cancelled (0: never). */
                'unpaid_cancel_hours' => $unpaid,
                /** The first lesson is at least this many hours away, so there is time to pay. */
                'notice_hours' => StartSlots::noticeHours(),
            ],
        ]]);
    }

    /**
     * A teacher's weekly times, or the open groups, for a plan.
     *
     * A private plan (teacher_id required): the teacher's weekly times on the viewer's clock, each free in the first four
     * weeks but at most one skipped for an academy holiday or the teacher's leave (no lesson that day; skips says which and
     * why), on whole and half hours, never when the learner already has a lesson, from the first day the family may start
     * (time to pay before an unpaid lesson would be cancelled), with each time's first lesson (the first week not skipped)
     * and a suggested pattern of per_week days (times with no week skipped first). Choose per_week of them (one a day) and send their keys. A group plan: the open groups of its shape
     * with a seat for the learner (teacher_id narrows them). 422 start-refused (teacher_unavailable, no_plan, ...).
     */
    public function slots(Request $request, StartSlots $slots, StartOwnCourse $start): JsonResponse
    {
        $valid = $request->validate([
            'learner_id' => ['nullable', 'string', 'max:64'],
            'subject_id' => ['required', 'uuid'],
            'plan_id' => ['required', 'uuid'],
            'teacher_id' => ['nullable', 'uuid'],
            'timezone' => ['nullable', Rule::in(Timezones::all())],
        ]);
        $me = $this->me($request);
        $learner = $this->family->learner($me, $valid['learner_id'] ?? null) ?? throw new NotFoundHttpException;
        $zone = $valid['timezone'] ?? $this->viewer->effective();
        try {
            $this->rules->assertCanStart($me);
            $this->rules->assertNoCourse($learner->id, $valid['subject_id']);
            [$plan] = $start->priced($valid['plan_id'], $me, $learner->id);
        } catch (StartRefused $e) {
            throw $this->problem($e);
        }

        if ($plan->group) {
            $facts = $this->rules->learnerFacts($learner->id);
            $found = $slots->groups($valid['subject_id'], $plan, $facts['age_group'], $facts['minor'], $zone, $valid['teacher_id'] ?? null);
            $starts = LessonGroup::query()->whereIn('id', array_column($found, 'id'))->pluck('min_to_start', 'id');
            $groups = [];
            foreach ($found as $g) {
                $times = [];
                foreach ($g['times'] as $t) {
                    $times[] = ['weekday' => (int) $t['weekday'], 'start' => (string) $t['start'], 'label' => $this->weekly((int) $t['weekday'], (string) $t['start'])];
                }
                $groups[] = [
                    'id' => (string) $g['id'],
                    'name' => $g['name'],
                    'teacher' => ['id' => (string) $g['teacher_id'], 'name' => $this->rules->teacherName((string) $g['teacher_id'], (string) $g['teacher_name'])],
                    /** forming (starts when it has min_to_start students) or running. */
                    'status' => (string) $g['status'],
                    'seats' => ['capacity' => (int) $g['capacity'], 'taken' => (int) $g['taken'], 'free' => (int) $g['free']],
                    'min_to_start' => $starts[$g['id']] ?? null,
                    'times' => $times,
                    /** Its next lesson, when it is running. */
                    'next' => $g['next'],
                    /** Lessons it held so far (a late join's are made up as the academy chose). */
                    'held' => (int) $g['held'],
                ];
            }

            return response()->json(['data' => ['format' => 'group', 'timezone' => $zone, 'per_week' => $plan->sessionsPerWeek, 'days' => [], 'suggested' => null, 'first_day' => null, 'groups' => $groups]]);
        }

        $teacher = isset($valid['teacher_id']) ? ($this->rules->teachers($learner->id, $valid['subject_id'], [$valid['teacher_id']])[$valid['teacher_id']] ?? null) : null;
        if ($teacher === null) {
            throw $this->problem(StartRefused::because(isset($valid['teacher_id']) ? 'teacher_unavailable' : 'times_needed'));
        }
        $free = $slots->private($teacher, $learner->id, $plan, $zone, $me);
        $byDay = [];
        foreach ($free as $key => $c) {
            $byDay[$c->studentWeekday][] = [
                /** Send back in times[] to start. */
                'key' => (string) $key,
                'start' => $c->studentTime,
                'end' => $c->studentEnd($plan->sessionMinutes),
                'label' => $this->weekly($c->studentWeekday, $c->studentTime),
                /** This weekly time's first lesson (the first week not skipped). */
                'first' => $this->time->present(CarbonImmutable::createFromTimestamp($c->occurrences[0], 'UTC'), $zone),
                /** Weeks among the first four with no lesson at this time (an academy holiday or the teacher's leave): never booked. */
                'skips' => $this->skipped($c->skipped, $zone),
            ];
        }
        $first = app()->getLocale() === 'ar' ? 6 : 1;
        uksort($byDay, fn (int $a, int $b) => ($a - $first + 7) % 7 <=> ($b - $first + 7) % 7);
        $days = [];
        foreach ($byDay as $weekday => $times) {
            usort($times, fn (array $a, array $b) => $a['start'] <=> $b['start']);
            $days[] = ['weekday' => (int) $weekday, 'name' => (string) __('scheduling::shifts.days.'.$weekday), 'times' => $times];
        }

        return response()->json(['data' => [
            'format' => 'private',
            'timezone' => $zone,
            /** How many weekly times to choose (a shared package: up to this many). */
            'per_week' => $plan->sessionsPerWeek,
            'days' => $days,
            /** A pattern of per_week days worth suggesting (keys); null when the teacher has too few days free. */
            'suggested' => $slots->suggest($free, $plan->sessionsPerWeek),
            /** The first day a course may start on (time to pay first). */
            'first_day' => $this->time->present(StartSlots::firstDay($zone), $zone),
            'groups' => [],
        ]]);
    }

    /**
     * Start a course.
     *
     * The plan at the block price the family saw (price_minor and currency, from the options: a different price today
     * refuses with price_changed and the new price), and either teacher_id with times (the keys of per_week weekly times,
     * one a day) or group_id. Makes the course, its lessons (waiting unpaid until the invoice is paid) and the first invoice
     * (the trial's offer on it by itself when open and bigger than the sibling discount), or joins the learner's shared
     * package (no invoice). Answers the course, the invoice with its lines, discounts and VAT, the pay link, and until when
     * to pay. One start at a time per learner. Refusals: 409 start-refused (time_taken with the fresh times,
     * price_changed, has_course and the course's pending ones with its enrollment_id), 409 guardian-consent-needed
     * (student_id: agree through POST family/consents/{student} and send again), 422 start-refused (anything else), 403
     * outside the classic look or while the academy enrols itself. Send an Idempotency-Key.
     */
    public function start(Request $request, StartOwnCourse $start): JsonResponse
    {
        $valid = $request->validate([
            'learner_id' => ['nullable', 'string', 'max:64'],
            'subject_id' => ['required', 'uuid'],
            'plan_id' => ['required', 'uuid'],
            /** The block price the family saw, in minor units (plans[].price.amount_minor). */
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'teacher_id' => ['nullable', 'uuid'],
            /** The weekly times' keys from GET family/start/slots. */
            'times' => ['nullable', 'array', 'max:7'],
            'times.*' => ['string', 'max:20'],
            'group_id' => ['nullable', 'uuid'],
            'timezone' => ['nullable', Rule::in(Timezones::all())],
        ]);
        $me = $this->me($request);
        $learner = $this->family->learner($me, $valid['learner_id'] ?? null) ?? throw new NotFoundHttpException;
        $zone = $valid['timezone'] ?? $this->viewer->effective();
        if (DB::table('subjects')->where('id', $valid['subject_id'])->doesntExist()) {
            throw new NotFoundHttpException;
        }

        try {
            $done = $start->handle(new OwnStart(
                viewerId: $me,
                learnerId: $learner->id,
                subjectId: $valid['subject_id'],
                planId: $valid['plan_id'],
                priceMinor: (int) $valid['price_minor'],
                currency: strtoupper((string) $valid['currency']),
                timezone: $zone,
                teacherId: $valid['teacher_id'] ?? null,
                times: array_values($valid['times'] ?? []),
                groupId: $valid['group_id'] ?? null,
            ));
        } catch (StartRefused $e) {
            throw $this->problem($e, $zone);
        }

        $invoice = $done->invoiceId === null ? null : $this->billing->invoiceSummary($done->invoiceId, $me);
        $plan = collect($this->billing->planOptions())->first(fn (PlanOption $p) => $p->id === $valid['plan_id']);
        $unpaid = StartSlots::unpaidHours();
        $payBy = $invoice !== null ? $done->payBy : null;     // the first invoice's due date too
        $group = $done->groupId === null ? null : LessonGroup::query()->find($done->groupId);
        $times = [];
        foreach ($done->weeklyTimes as $at) {
            $local = $at->setTimezone($zone);
            $times[] = ['weekday' => (int) $local->dayOfWeekIso, 'start' => $local->format('H:i'), 'label' => $this->weekly($local->dayOfWeekIso, $local->format('H:i'))];
        }
        $payText = match (true) {
            $invoice === null && $done->joinedPackage => __('scheduling::start.pay.'.($this->billing->courseBilling($done->enrollmentId)?->active ? 'package' : 'package_unpaid')),
            $group !== null && $group->status === LessonGroup::FORMING => __('scheduling::start.pay.group_forming'),
            $payBy !== null => trans_choice('scheduling::start.pay.by', (int) $unpaid, ['when' => $this->time->present($payBy, $zone)['label'] ?? '']),
            default => __('scheduling::start.pay.any_time'),
        };
        $own = CourseStart::query()->where('enrollment_id', $done->enrollmentId)->first();

        return response()->json(['data' => [
            'enrollment_id' => $done->enrollmentId,
            /** pending_payment until the first invoice is paid; active at once when it joined a paid shared package. */
            'status' => (string) DB::table('enrollments')->where('id', $done->enrollmentId)->value('status'),
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            'subject' => ['id' => $valid['subject_id'], 'name' => $this->rules->subjectName($valid['subject_id'])],
            'teacher' => ['id' => $done->teacherId, 'name' => $this->rules->teacherName($done->teacherId)],
            'plan' => $plan === null ? null : ['id' => $plan->id, 'name' => $plan->name, 'format' => $plan->group ? 'group' : 'private', 'session_minutes' => $plan->sessionMinutes,
                'sessions_per_week' => $plan->sessionsPerWeek, 'sessions_per_block' => $plan->sessionsPerBlock],
            /** The weekly times on the viewer's clock. */
            'weekly_times' => $times,
            'first_lesson' => $this->time->present($done->firstLesson, $zone),
            /** Lessons booked so far (more are made week by week). */
            'lessons' => $done->lessons,
            /** Days with no lesson (Y-m-d on the teacher's clock): the skipped weeks below, and any the teacher or the learner was already booked on. */
            'skipped_dates' => array_values(array_map('strval', $done->skippedDates)),
            /** The first weeks the course starts without (an academy holiday or the teacher's leave), as GET family/start/slots gave them. */
            'skipped' => $this->skipped($done->skippedWeeks, $zone),
            /** It joined the learner's shared package: its lessons draw on that balance, with no invoice of its own. */
            'joined_package' => (bool) $done->joinedPackage,
            'group' => $group === null ? null : [
                'id' => $group->id,
                'status' => $group->status,
                'seat' => $done->seat === null ? null : (int) $done->seat,
                'capacity' => $done->capacity === null ? null : (int) $done->capacity,
                /** It started with this learner. */
                'started' => $done->groupStarted,
                'min_to_start' => $group->min_to_start,
            ],
            /** The first invoice as the payer reads it (lines, discounts, VAT, total; coupon entry); null when it joined a shared package. */
            'invoice' => $invoice?->present($this->time),
            /** The academy's signed pay page for it (the apps pay through POST family/invoices/{id}/checkout as well). */
            'pay_url' => $invoice?->payUrl,
            'offer' => [
                /** applied, ended (its time passed: the plain price), family_discount_bigger, not_for_plan, package or none. */
                'state' => $done->offer,
                'percent' => $done->offerPercentBp === null ? null : $done->offerPercentBp / 100,
                /** What became of it, in a sentence (null: no offer). */
                'message' => $done->offer === 'none' ? null : (string) __('scheduling::start.offer.'.$done->offer, ['percent' => ($done->offerPercentBp ?? 0) / 100]),
            ],
            'payment' => [
                /** Pay before this to keep the first lesson (rule 16), the first invoice's due date too; null when nothing is due or no lesson is booked yet. */
                'pay_by' => $this->time->present($payBy, $zone),
                'unpaid_cancel_hours' => $unpaid,
                'text' => (string) $payText,
                /** When the next block's invoice comes, and that the family can stop any time. */
                'renews' => $plan === null ? null : trans_choice('scheduling::start.renews', (int) $plan->renewAtSessions),
            ],
            /** The family may call it off before paying (POST family/courses/{enrollment}/cancel). */
            'can_cancel' => $own !== null && app(CancelOwnStart::class)->whyNot($own) === null,
        ]], 201);
    }

    /**
     * Call off a course I started, before paying.
     *
     * Only its payer, while it waits for its first payment and no money may be on its way (nothing paid, no proof waiting,
     * no payment page open). Its lessons are cancelled with nothing charged, its invoice is voided and the teacher's times
     * are free again; the learner may start again. 409 start-refused with why not. Send an Idempotency-Key.
     */
    public function cancel(Request $request, string $enrollment, CancelOwnStart $cancel): JsonResponse
    {
        $me = $this->me($request);
        try {
            $done = $cancel->handle($enrollment, $me);
        } catch (StartRefused $e) {
            if ($e->httpStatus === 404) {
                throw new NotFoundHttpException;
            }
            throw $this->problem($e);
        }

        return response()->json(['data' => [
            'enrollment_id' => $enrollment,
            'status' => 'ended',
            'lessons_cancelled' => $done['lessons'],
            /** The invoice voided with it. */
            'invoice_number' => $done['invoice'],
            'message' => (string) __('scheduling::start.cancelled'),
        ]]);
    }

    /** The subjects some active teacher takes new students or groups in. @return array<string, true> */
    private function taughtSubjects(): array
    {
        return DB::table('teacher_subjects as ts')
            ->join('teacher_profiles as tp', fn ($j) => $j->on('tp.academy_id', '=', 'ts.academy_id')->on('tp.user_id', '=', 'ts.teacher_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'tp.academy_id')->on('u.id', '=', 'tp.user_id'))
            ->where('tp.status', 'active')->where('u.status', 'active')
            ->where(fn ($q) => $q->where('tp.accepts_new', true)->orWhere('tp.accepts_groups', true))
            ->distinct()->pluck('ts.subject_id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();
    }

    /** @return array<string, mixed> a plan as the family chooses it, priced for this payer and learner */
    private function plan(PlanOption $plan, Money $price, string $regionId, string $payerId, string $learnerId, ?string $offerCode): array
    {
        $quote = $this->billing->quote($plan->id, $regionId, $payerId, $learnerId, $offerCode);
        if ($quote !== null && $quote->couponError !== null) {
            $quote = $this->billing->quote($plan->id, $regionId, $payerId, $learnerId);
        }
        $lesson = intdiv(2 * $price->minor + max(1, $plan->sessionsPerBlock), 2 * max(1, $plan->sessionsPerBlock));
        $discounts = [];
        foreach ($quote->discounts ?? [] as $d) {
            $discounts[] = ['label' => (string) $d['label'], 'amount_minor' => -$d['amount']->minor, 'amount' => Money::of(-$d['amount']->minor, $price->currency)->format()];
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            /** private (one student a lesson) or group (a seat in a group). */
            'format' => $plan->group ? 'group' : 'private',
            /** A shared package: one balance for the learner's courses on it (a later course joins it, with no invoice). */
            'shared' => $plan->shared,
            'session_minutes' => $plan->sessionMinutes,
            'sessions_per_week' => $plan->sessionsPerWeek,
            'sessions_per_block' => $plan->sessionsPerBlock,
            'validity_days' => $plan->validityDays,
            /** The next block's invoice comes when this many lessons are left. */
            'renews_at_lessons_left' => $plan->renewAtSessions,
            /** The block's price: send price.amount_minor and price.currency back to start. */
            'price' => ['amount_minor' => $price->minor, 'currency' => $price->currency, 'amount' => $price->format()],
            'per_lesson' => ['amount_minor' => $lesson, 'currency' => $price->currency, 'amount' => Money::of($lesson, $price->currency)->format()],
            /** What the first invoice would come to for this learner before VAT is added: the sibling discount or the trial's offer (the bigger). */
            'first_invoice' => [
                'discounts' => $discounts,
                'total' => $quote === null ? null : ['amount_minor' => $quote->total->minor, 'currency' => $quote->total->currency, 'amount' => $quote->total->format()],
                /** The trial's offer is the discount here (open, for this plan, and bigger than the sibling discount). */
                'offer_applied' => $offerCode !== null && $quote !== null && $quote->couponError === null && $quote->couponNote === null,
            ],
        ];
    }

    /** A weekly time as a family reads it ("الثلاثاء 6:00 م"). */
    /**
     * Weeks with no lesson, on the viewer's clock: the day (Y-m-d), why (holiday: the academy's; teacher_leave: never the
     * leave's own reason), when the lesson would have been, and the note to show («لا حصة يوم الثلاثاء 17 نوفمبر: إجازة الأكاديمية»).
     *
     * @param  list<array{at: int, date: string, reason: string}>  $weeks
     * @return list<array{date: string, reason: string, at: array<string, string>|null, text: string}>
     */
    private function skipped(array $weeks, string $zone): array
    {
        $out = [];
        foreach ($weeks as $week) {
            $at = CarbonImmutable::createFromTimestamp($week['at'], 'UTC');
            $day = $at->setTimezone($zone);
            $out[] = [
                'date' => $day->toDateString(),
                'reason' => (string) $week['reason'],
                'at' => $this->time->present($at, $zone),
                'text' => (string) __('scheduling::start.skipped.'.$week['reason'], ['day' => $day->locale(app()->getLocale())->translatedFormat('l j F')]),
            ];
        }

        return $out;
    }

    private function weekly(int $weekday, string $start): string
    {
        return __('scheduling::shifts.days.'.$weekday).' '.Clock::wall($start);
    }

    /** A refusal as a problem: its reason, its sentence and what the client needs to act on it. */
    private function problem(StartRefused $e, ?string $zone = null): ProblemException
    {
        $extra = $e->extra;
        if ($e->reason === 'time_taken') {
            $zone ??= $this->viewer->effective();
            $times = [];
            foreach ($extra['times'] ?? [] as $t) {
                $times[] = $t + ['label' => $this->weekly((int) $t['weekday'], (string) $t['start'])];
            }
            $extra['times'] = $times;
        }

        return new ProblemException(
            $e->httpStatus,
            $e->reason === 'consent' ? 'guardian-consent-needed' : 'start-refused',
            (string) $e->validator->errors()->first('start'),
            ['reason' => $e->reason, 'errors' => $e->errors()] + $extra,
        );
    }

    private function me(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
