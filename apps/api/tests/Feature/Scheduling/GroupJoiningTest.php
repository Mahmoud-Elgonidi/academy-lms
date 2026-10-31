<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Plan;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\Groups\BookGroupMakeup;
use Modules\Scheduling\Actions\Groups\JoinGroup;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * Taking a seat (docs/architecture/02, "الحصص الجماعية"): a group plan of
 * the group's shape, members who may learn together, each student's seats
 * paid from their own credit, and a late join's missed lessons made up for
 * as the office chooses: off the first invoice, private makeup lessons, or
 * a seat in another group's lesson.
 */

beforeEach(function () {
    $this->setUpGroups();
    // The teacher's free mornings, for private makeup lessons.
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([1, 2, 6] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '10:00', 'duration_min' => 240]);
    }
});

afterEach(fn () => CarbonImmutable::setTestNow());

$refusal = function (Closure $do): string {
    try {
        $do();
    } catch (ValidationException $e) {
        return (string) collect($e->errors())->flatten()->first();
    }

    return '';
};

/** The group's first $held lessons taught, and the clock just after them. */
$hold = function ($group, int $held): void {
    $lessons = Session::query()->where('group_id', $group->id)->orderBy('starts_at')->take($held)->get();
    Session::query()->whereIn('id', $lessons->modelKeys())->update(['status' => 'completed']);
    CarbonImmutable::setTestNow($lessons->last()->ends_at->addHour());
};

it('pays for each student\'s seats from their own credit', function () {
    $group = $this->group();
    $a = $this->join($group, $this->student());
    $b = $this->join($group, $this->student());
    $first = Session::query()->where('group_id', $group->id)->orderBy('starts_at')->first();
    $seat = fn (string $enrollment) => SessionParticipant::query()->where('session_id', $first->id)->where('enrollment_id', $enrollment)->value('funding_status');

    $this->pay($a);
    expect($seat($a->enrollmentId))->toBe('funded')->and($seat($b->enrollmentId))->toBe('unfunded')
        ->and($first->refresh()->funding_status)->toBe(FundingStatus::Unfunded)
        ->and(SessionParticipant::query()->where('enrollment_id', $a->enrollmentId)->where('funding_status', 'funded')->count())->toBe(8);

    $this->pay($b);
    expect($seat($b->enrollmentId))->toBe('funded')
        ->and($first->refresh()->funding_status)->toBe(FundingStatus::Funded)
        ->and(CreditReservation::query()->where('session_id', $first->id)->pluck('account_id')->unique())->toHaveCount(2)
        ->and(CreditReservation::query()->where('status', 'held')->sum('minutes'))->toEqual(480);
});

it('takes the lessons a late student missed off their first invoice, at the lesson\'s price', function () use ($hold) {
    $group = $this->group();
    $this->join($group, $this->student());
    $hold($group, 3);
    $late = $this->student();

    $preview = app(JoinGroup::class)->preview($group->id, $this->groupPlan->id);
    expect($preview)->toMatchArray(['seat' => 2, 'capacity' => 3, 'missed' => 3, 'late_join' => 'discount']);
    $quote = app(BillingApi::class)->quote($this->groupPlan->id, $this->region->id, $this->payer->id, $late->id, null, 3);
    expect($quote->discounts[0]['label'])->toBe(__('billing::discounts.quote.late_join', ['count' => 3]))
        ->and($quote->discounts[0]['amount']->minor)->toBe(9000)
        ->and($quote->total->minor)->toBe(15000);

    $joined = $this->join($group, $late);
    $line = InvoiceLine::query()->where('invoice_id', $joined->invoice->id)->where('kind', 'discount')->first();
    expect($joined->missed)->toBe(3)->and($joined->lateJoin)->toBe('discount')->and($joined->lateJoinLessons)->toBe(3)
        ->and($line->amount_minor)->toBe(-9000)
        ->and($line->meta['source'])->toBe('late_join')
        ->and($line->meta['lessons'])->toBe(3)
        ->and(json_decode(DB::table('audit_logs')->where('action', 'lesson_group.joined')->orderByDesc('created_at')->value('data'), true))
        ->toMatchArray(['missed' => 3, 'late_join' => 'discount', 'lessons' => 3, 'seat' => 2]);

    // The office may count fewer, never more.
    $fewer = $this->join($group, $this->student(), lessons: 9);
    expect($fewer->lateJoinLessons)->toBe(3);
});

it('gives private makeup credit for missed lessons, booked with the group\'s teacher alone', function () use ($hold, $refusal) {
    $group = $this->group();
    $this->join($group, $this->student());
    $hold($group, 2);
    $joined = $this->join($group, $this->student(), 'private');

    $lot = CreditLot::query()->where('source', 'makeup')->first();
    expect($joined->lateJoin)->toBe('private')
        ->and($lot->granted_min)->toBe(60)->and($lot->use_for)->toBe('private')
        ->and(InvoiceLine::query()->where('invoice_id', $joined->invoice->id)->where('kind', 'discount')->exists())->toBeFalse();

    $makeup = app(BookMakeup::class)->handle($joined->enrollmentId, CarbonImmutable::parse('2026-10-12 11:00', 'Africa/Cairo'), $this->admin->id);
    expect($makeup->funding_status)->toBe(FundingStatus::Makeup)->and($makeup->durationMinutes())->toBe(30);

    // Kept for private lessons: no seat in another group's lesson with it.
    $other = $this->otherGroup();
    $lesson = Session::query()->where('group_id', $other->id)->orderBy('starts_at')->first();
    expect($refusal(fn () => app(BookGroupMakeup::class)->handle($lesson->id, $joined->enrollmentId, $this->admin->id)))
        ->toBe(__('scheduling::groups.errors.no_group_makeup'));
});

it('gives group makeup credit for missed lessons, a free seat in another group\'s lesson of the subject', function () use ($hold, $refusal) {
    $group = $this->group();
    $this->join($group, $this->student());
    $hold($group, 2);
    $student = $this->student();
    $joined = $this->join($group, $student, 'group', 1);
    $other = $this->otherGroup();
    $lesson = Session::query()->where('group_id', $other->id)->orderBy('starts_at')->first();

    expect(CreditLot::query()->where('source', 'makeup')->value('use_for'))->toBe('group')
        ->and($refusal(fn () => app(BookMakeup::class)->handle($joined->enrollmentId, CarbonImmutable::parse('2026-10-12 11:00', 'Africa/Cairo'), $this->admin->id)))
        ->toBe(__('scheduling::policy.errors.no_makeup_credit'))
        ->and($refusal(fn () => app(BookGroupMakeup::class)->handle(Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->orderBy('starts_at')->value('id'), $joined->enrollmentId, $this->admin->id)))
        ->toBe(__('scheduling::groups.errors.own_group'));

    app(BookGroupMakeup::class)->handle($lesson->id, $joined->enrollmentId, $this->admin->id);
    $seat = SessionParticipant::query()->where('session_id', $lesson->id)->where('user_id', $student->id)->first();
    expect($seat->funding_status)->toBe('makeup')
        ->and(CreditReservation::query()->where('session_id', $lesson->id)->count())->toBe(1)
        ->and(CreditLot::query()->where('source', 'makeup')->value('remaining_min'))->toBe(0)
        // One lesson's credit only: no second seat with it.
        ->and($refusal(fn () => app(BookGroupMakeup::class)->handle(Session::query()->where('group_id', $other->id)->orderBy('starts_at')->skip(1)->value('id'), $joined->enrollmentId, $this->admin->id)))
        ->toBe(__('scheduling::groups.errors.no_group_makeup'));
});

it('takes only a group plan of the group\'s shape, and private lessons only a private plan', function () use ($refusal) {
    $group = $this->group();
    $student = $this->student();
    $weekly = Plan::query()->create(['name_ar' => 'مجموعة أسبوعية', 'name_en' => 'Weekly group', 'session_minutes' => 30, 'sessions_per_block' => 4, 'sessions_per_week' => 1, 'validity_days' => 42, 'lesson_format' => 'group']);
    $weekly->prices()->create(['price_region_id' => $this->region->id, 'amount_minor' => 12000, 'currency' => 'SAR']);

    expect($refusal(fn () => $this->join($group, $student, planId: $this->privatePlan->id)))->toBe(__('scheduling::groups.errors.plan_not_group'))
        ->and($refusal(fn () => $this->join($group, $student, planId: $weekly->id)))->toBe(__('scheduling::groups.errors.plan_shape', ['minutes' => 30, 'per_week' => 2]))
        ->and(collect(app(BillingApi::class)->planOptions(null, 'group'))->pluck('id')->sort()->values()->all())->toBe(collect([$this->groupPlan->id, $weekly->id])->sort()->values()->all())
        ->and(collect(app(BillingApi::class)->planOptions(null, 'group'))->every(fn ($p) => $p->group))->toBeTrue()
        ->and(collect(app(BillingApi::class)->planOptions(null, 'private'))->pluck('id')->all())->toBe([$this->privatePlan->id]);

    $times = [CarbonImmutable::parse('2026-10-12 10:00', 'Africa/Cairo')->getTimestamp(), CarbonImmutable::parse('2026-10-13 10:00', 'Africa/Cairo')->getTimestamp()];
    $batch = app(PlaceBookingHold::class)->handle($this->teacher->id, $student->id, $times, 30, $this->admin->id);
    expect($refusal(fn () => app(EnrollStudent::class)->handle($batch, $student->id, $this->quran, new EnrollmentTerms($this->groupPlan->id, $this->payer->id, $this->region->id), $this->admin->id)))
        ->toBe(__('scheduling::groups.errors.plan_is_group'));
});

it('fixes a plan\'s format once anyone is on it, and never makes a group plan a shared package', function () use ($refusal) {
    $plan = fn (array $with) => $with + ['name_ar' => 'باقة', 'name_en' => 'Plan', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2,
        'prices' => [['price_region_id' => $this->region->id, 'amount' => '240']]];

    expect($refusal(fn () => app(SavePlan::class)->handle(null, $plan(['lesson_format' => 'group', 'credit_scope' => 'student_pool']))))->toBe(__('billing::plans.errors.group_shared'))
        ->and($refusal(fn () => app(SavePlan::class)->handle(null, $plan(['lesson_format' => 'class']))))->toBe(__('billing::plans.errors.format'))
        ->and(app(SavePlan::class)->handle(null, $plan(['lesson_format' => 'group']))->lesson_format)->toBe('group');

    // Free to change while no one is on it; fixed once someone is.
    app(SavePlan::class)->handle($this->groupPlan, $plan(['lesson_format' => 'private']));
    app(SavePlan::class)->handle($this->groupPlan->refresh(), $plan(['lesson_format' => 'group']));
    $this->join($this->group(), $this->student());
    expect($refusal(fn () => app(SavePlan::class)->handle($this->groupPlan->refresh(), $plan(['lesson_format' => 'private']))))->toBe(__('billing::plans.errors.format_locked'))
        ->and(app(SavePlan::class)->handle($this->groupPlan->refresh(), $plan(['name_en' => 'Renamed']))->lesson_format)->toBe('group');
});

it('keeps minors and adults, and age groups, apart unless the academy mixes them', function () use ($refusal) {
    $group = $this->group(4);
    $this->join($group, $this->student());          // an adult: the group is for adults now
    expect($group->refresh()->age_group)->toBe('adults');

    $teen = $this->student('2010-03-01');
    expect($refusal(fn () => $this->join($group, $teen)))->toBe(__('scheduling::groups.errors.age_band', ['age' => __('people::people.age_groups.adults')]));

    // No ages set and a first member of unknown age: minors still never sit with adults.
    $open = $this->group(4, teacherId: tap(User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']), fn ($t) => app(PeopleApi::class)
        ->saveTeacherProfile(new TeacherProfileData($t->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 4)))->id);
    $unknown = User::factory()->withRole(Role::Student)->create();       // no birth date, no guardian: an adult of unknown age
    $this->join($open, $unknown);
    expect($refusal(fn () => $this->join($open, $teen)))->toBe(__('scheduling::groups.errors.minors_adults'));

    SchedulingSettings::query()->create(['allow_mixed_ages' => true]);
    expect($this->join($group, $teen)->seat)->toBe(2);
});
