<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Communication\Models\Notification;
use Modules\Credits\Models\CreditLot;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\Holidays\DeclareHoliday;
use Modules\Scheduling\Actions\Holidays\RemoveHoliday;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\RefuseSubstitute;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Actions\TimeOff\AssignSubstitutes;
use Modules\Scheduling\Actions\TimeOff\CancelUncoveredLessons;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Actions\TimeOff\WithdrawTimeOff;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Pages\TimeOffReview;
use Modules\Scheduling\Filament\Teacher\MyTimeOff;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Queries\SubstituteCandidates;
use Modules\Scheduling\Queries\TimeOffCover;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\ZoneHint;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Teacher time off, substitutes and academy holidays (docs/architecture/01,
 * flow "ز"; 02, rules 15 and 21). The fixture: Monday 5 October 2026, 10:00
 * in Cairo; the student's lessons are Tuesdays and Saturdays 18:00 Cairo
 * (15:00 UTC) with a Cairo teacher; a Riyadh teacher teaches Quran too and
 * is on shift then.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();
    $this->saturday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->first();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function leave(string $first, ?string $last = null, ?string $from = null, ?string $to = null, bool $approve = true): TeacherTimeOff
{
    $timeOff = app(RequestTimeOff::class)->handle(test()->teacher->id, $first, $last ?? $first, $from, $to, 'sick', null, test()->teacher->id);

    return $approve ? app(DecideTimeOff::class)->handle($timeOff, true, null, test()->admin->id) : $timeOff;
}

function sentTo(User $user, string $template): int
{
    return Notification::query()->where('user_id', $user->id)->where('template_key', $template)->count();
}

it('takes whole days on the teacher\'s own clock, or some hours, and tells whoever decides', function () {
    $days = leave('2026-10-06', '2026-10-07', approve: false);
    $hours = app(RequestTimeOff::class)->handle($this->teacher->id, '2026-10-10', '2026-10-10', '17:30', '19:00', 'personal', 'Doctor', $this->teacher->id);

    expect($days->status)->toBe(TimeOffStatus::Pending)
        ->and($days->starts_at->toIso8601String())->toBe('2026-10-05T21:00:00+00:00')       // midnight in Cairo (UTC+3 until the end of October)
        ->and($days->ends_at->toIso8601String())->toBe('2026-10-07T21:00:00+00:00')         // the midnight after the last day
        ->and([$hours->all_day, $hours->starts_at->format('H:i'), $hours->ends_at->format('H:i')])->toBe([false, '14:30', '16:00'])
        ->and(sentTo($this->admin, 'scheduling.timeoff_requested'))->toBe(2);
});

it('refuses overlapping time off and days already gone, and lets the teacher take a request back', function () {
    $first = leave('2026-10-06', '2026-10-08', approve: false);

    expect(fn () => leave('2026-10-08', '2026-10-09', approve: false))->toThrow(ValidationException::class)
        ->and(fn () => leave('2026-10-04', approve: false))->toThrow(ValidationException::class)
        ->and(fn () => app(RequestTimeOff::class)->handle($this->teacher->id, '2026-10-10', '2026-10-10', '19:00', '18:00', 'other', null, $this->teacher->id))->toThrow(ValidationException::class);

    app(WithdrawTimeOff::class)->handle($first, $this->teacher->id);
    expect($first->refresh()->status)->toBe(TimeOffStatus::Withdrawn)
        ->and(leave('2026-10-08', approve: false)->status)->toBe(TimeOffStatus::Pending);
});

it('takes approved time off out of the teacher\'s availability, and finds substitutes who teach the subject and are free', function () {
    $timeOff = leave('2026-10-06');
    $tuesday = [CarbonImmutable::parse('2026-10-06', 'Africa/Cairo')->utc(), CarbonImmutable::parse('2026-10-07', 'Africa/Cairo')->utc()];

    $free = app(AvailabilityQuery::class)->forTeachers([$this->teacher->id, $this->other->id], ...$tuesday);
    $lessons = app(TimeOffCover::class)->lessons($timeOff);

    expect($free[$this->teacher->id]->free->totalSeconds())->toBe(0)
        ->and($free[$this->other->id]->free->totalSeconds())->toBeGreaterThan(0)
        ->and(array_map(fn ($s) => $s->id, $lessons))->toBe([$this->tuesday->id])
        ->and(TimeOffCover::uncovered())->toBe(1)
        ->and(array_column(app(SubstituteCandidates::class)->forSessions($lessons)[$this->tuesday->id], 'id'))->toBe([$this->other->id])
        ->and(sentTo($this->teacher, 'scheduling.timeoff_answered'))->toBe(1);
});

it('gives a lesson to a substitute who remembers whose it is, and tells the family and the substitute', function () {
    leave('2026-10-06');

    app(SubstituteTeacher::class)->handle($this->tuesday, $this->other->id, $this->admin->id);

    $lesson = $this->tuesday->refresh();
    expect([$lesson->teacher_id, $lesson->original_teacher_id, $lesson->status])->toBe([$this->other->id, $this->teacher->id, SessionStatus::Scheduled])
        ->and($lesson->participant(ParticipantRole::Teacher)->user_id)->toBe($this->other->id)
        ->and(TimeOffCover::uncovered())->toBe(0)
        ->and(sentTo($this->guardian, 'scheduling.substitute_family'))->toBe(1)
        ->and(sentTo($this->other, 'scheduling.substitute_teacher'))->toBe(1)
        // Its own teacher is on leave then: it cannot go back to them.
        ->and(fn () => app(SubstituteTeacher::class)->handle($lesson, $this->teacher->id, $this->admin->id))->toThrow(ValidationException::class);

    // Nobody who does not teach the subject, or is not free.
    $stranger = User::factory()->withRole(Role::Teacher)->create();
    expect(fn () => app(SubstituteTeacher::class)->handle($this->saturday, $stranger->id, $this->admin->id))->toThrow(ValidationException::class);
});

it('lets the family turn the substitute down for a makeup, as the academy\'s doing', function () {
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    leave('2026-10-06');
    app(SubstituteTeacher::class)->handle($this->tuesday, $this->other->id, $this->admin->id);

    app(RefuseSubstitute::class)->handle($this->tuesday->refresh(), null, $this->guardian->id);

    expect($this->tuesday->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($this->tuesday->decision)->toMatchArray(['by' => 'family', 'reason' => 'substitute_refused', 'rule' => 21, 'credit' => 'makeup'])
        ->and(fn () => app(RefuseSubstitute::class)->handle($this->saturday, null, $this->guardian->id))->toThrow(ValidationException::class);   // no substitute there
});

it('covers every lesson of a leave at once, and cancels what nobody covered an hour before, with a makeup', function () {
    $both = leave('2026-10-06', '2026-10-10');
    expect(app(AssignSubstitutes::class)->handle($both, $this->admin->id))->toBe(['covered' => 2, 'left' => 0])
        ->and(Session::query()->whereIn('id', [$this->tuesday->id, $this->saturday->id])->pluck('teacher_id')->unique()->all())->toBe([$this->other->id]);

    // Another week: nobody covers the Tuesday lesson; at 17:10 in Cairo it is cancelled (the lesson is at 18:00).
    $next = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse('2026-10-13 18:00', 'Africa/Cairo')->utc())->firstOrFail();
    app(DecideTimeOff::class)->handle(app(RequestTimeOff::class)->handle($this->teacher->id, '2026-10-13', '2026-10-13', null, null, 'vacation', null, $this->teacher->id), true, null, $this->admin->id);

    expect(app(CancelUncoveredLessons::class)->handle(CarbonImmutable::parse('2026-10-13 16:30', 'Africa/Cairo')))->toBe(0)
        ->and(app(CancelUncoveredLessons::class)->handle(CarbonImmutable::parse('2026-10-13 17:10', 'Africa/Cairo')))->toBe(1)
        ->and($next->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($next->decision)->toMatchArray(['by' => 'system', 'reason' => 'teacher_leave', 'rule' => 21, 'credit' => 'makeup']);
});

it('closes the academy on a holiday: lessons cancelled with their minutes back, the credit lasting longer, one message each', function () {
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $account = Enrollment::query()->findOrFail($this->booked->enrollmentId)->credit_account_id;
    $expiry = CreditLot::query()->where('account_id', $account)->value('expires_at');

    expect(app(DeclareHoliday::class)->affected('2026-10-10', '2026-10-11')->pluck('id')->all())->toBe([$this->saturday->id]);
    $holiday = app(DeclareHoliday::class)->handle('National Day', '2026-10-10', '2026-10-11', $this->admin->id);

    expect($this->saturday->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($this->saturday->decision)->toMatchArray(['by' => 'system', 'reason' => 'holiday', 'rule' => 15, 'credit' => 'refund'])
        ->and($this->tuesday->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and($holiday->cancelled_count)->toBe(1)
        ->and(CreditLot::query()->where('account_id', $account)->value('expires_at')->diffInDays($expiry, true))->toEqual(2)
        ->and(sentTo($this->guardian, 'scheduling.holiday_family'))->toBe(1)
        ->and(sentTo($this->guardian, 'scheduling.session_cancelled'))->toBe(0)
        ->and(sentTo($this->teacher, 'scheduling.holiday_teacher'))->toBe(1)
        ->and(app(AvailabilityQuery::class)->forTeachers([$this->other->id], $holiday->starts_at, $holiday->ends_at)[$this->other->id]->free->totalSeconds())->toBe(0)
        ->and(fn () => app(DeclareHoliday::class)->handle('Overlap', '2026-10-11', '2026-10-12', null))->toThrow(ValidationException::class);

    // Taken back before it begins; not once it has.
    $later = app(DeclareHoliday::class)->handle('Later', '2026-11-03', '2026-11-03', null);
    app(RemoveHoliday::class)->handle($later);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 09:00', 'Africa/Cairo'));
    expect(fn () => app(RemoveHoliday::class)->handle($holiday))->toThrow(ValidationException::class);
});

it('makes no lessons on holidays when it makes the coming weeks', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00', 'Africa/Cairo'));
    app(DeclareHoliday::class)->handle('Closed', '2026-12-01', '2026-12-01', null);

    app(MaterializeSessions::class)->handle([$this->booked->enrollmentId]);
    $days = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->pluck('starts_at')
        ->map(fn ($at) => $at->setTimezone('Africa/Cairo')->toDateString());

    expect($days)->not->toContain('2026-12-01')->toContain('2026-12-05');
});

it('takes some hours from the teacher\'s own pickers, written on their clock both ways', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyTimeOff::class)
        ->mountAction('ask')
        ->setActionData(['kind' => 'hours', 'first_day' => '2026-10-06', 'from' => '17:00', 'to' => '19:00', 'reason' => 'personal'])
        ->assertMountedActionModalSeeHtml('h:mm A')                     // the pickers show 5:00 PM; what they keep stays 17:00
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('5:00 PM')->assertSee('7:00 PM');

    $timeOff = TeacherTimeOff::query()->where('teacher_id', $this->teacher->id)->sole();
    expect($timeOff->all_day)->toBeFalse()
        ->and($timeOff->starts_at->setTimezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-10-06 17:00')
        ->and($timeOff->ends_at->setTimezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-10-06 19:00');

    $this->teacher->update(['time_format' => 'h24']);
    $this->actingAs($this->teacher->fresh());
    Livewire::test(MyTimeOff::class)->assertSee('17:00')->assertSee('19:00')->assertDontSee('5:00 PM')
        ->mountAction('ask')->setActionData(['kind' => 'hours'])->assertMountedActionModalSeeHtml('HH:mm');
});

it('lets the teacher ask from their area and the academy approve and cover from the back office', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyTimeOff::class)
        ->callAction('ask', data: ['kind' => 'days', 'first_day' => '2026-10-06', 'last_day' => '2026-10-06', 'reason' => 'sick', 'note' => 'Fever'])
        ->assertHasNoActionErrors()
        ->assertSee(__('scheduling::timeoff.statuses.pending'));
    $timeOff = TeacherTimeOff::query()->where('teacher_id', $this->teacher->id)->firstOrFail();

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(TimeOffReview::class, ['timeOff' => $timeOff->id])
        ->assertSee($this->student->name)
        ->assertSee(ZoneHint::lessonsWithTeacher())   // the lessons on my clock, the teacher's beside them
        ->callAction('approve', data: ['note' => 'Get well'])
        ->assertHasNoActionErrors()
        ->set('pick.'.$this->tuesday->id, $this->other->id)
        ->call('assign', $this->tuesday->id)
        ->assertSee(__('scheduling::timeoff.review.states.covered', ['name' => $this->other->name]));

    expect($timeOff->refresh()->status)->toBe(TimeOffStatus::Approved)
        ->and($this->tuesday->refresh()->teacher_id)->toBe($this->other->id);

    // The family sees whose place the substitute takes, and can turn them down.
    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    Livewire::test(FamilyLessons::class)
        ->assertSee(__('scheduling::timeoff.family.substitute', ['name' => $this->teacher->name]))
        ->callAction('refuse', arguments: ['session' => $this->tuesday->id])
        ->assertHasNoActionErrors();
    expect($this->tuesday->refresh()->decision)->toMatchArray(['reason' => 'substitute_refused', 'credit' => 'makeup']);
});

it('keeps time off to those who decide it, and each teacher to their own', function () {
    $this->clearTenant();
    $accountant = (function () {
        $this->actAsTenant($this->academy);
        $user = User::factory()->withRole(Role::Accountant)->create();
        $this->clearTenant();

        return $user;
    })();

    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/time-off')->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($accountant)->get('http://noor.lms.test/admin/time-off')->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/teach/time-off')->assertOk();
});
