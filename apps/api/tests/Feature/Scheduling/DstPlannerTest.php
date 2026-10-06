<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Models\Notification;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\Dst\DecideDstChange;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\ShiftTeacherLessons;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Pages\DstChanges;
use Modules\SharedKernel\Time\Timezones;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The DST planner and bulk rescheduling (docs/architecture/01, "مخطِّط
 * التوقيت الصيفي" and "إعادة جدولة جماعية"; 02, section 1), on the real
 * transitions of autumn 2026: London goes back an hour on 25 October,
 * Cairo on the night of 29-30 October; London forward on 28 March 2027,
 * Cairo on 30 April. The fixture's lessons are Tuesdays and Saturdays at
 * 18:00 Cairo, with a Cairo teacher on shift 17:00-21:00.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function notices(): array
{
    return ShiftNotice::query()->orderBy('changes_at')->get()->map(fn (ShiftNotice $n) => [
        $n->changes_at->toIso8601ZuluString(), substr($n->before_time, 0, 5), substr($n->after_time, 0, 5), $n->delta_min, $n->cause_tz, $n->reverts_at?->toIso8601ZuluString(),
    ])->all();
}

function cairo(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'Africa/Cairo')->utc();
}

function lessonAt(string $cairo): ?Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', cairo($cairo))->first();
}

function told(User $user, string $template): int
{
    return Notification::query()->where('user_id', $user->id)->where('template_key', $template)->count();
}

it('finds where the student\'s hour moves, whose clock changed, and until when the clocks keep that difference', function () {
    expect(app(PlanDstChanges::class)->handle())->toBe(2)
        ->and(notices())->toBe([
            // London goes back first: the Tuesday lesson comes an hour earlier for the student, until Cairo goes back too.
            ['2026-10-27T15:00:00Z', '16:00', '15:00', -60, 'Europe/London', '2026-10-29T21:00:00Z'],
            // Then Cairo: back to 16:00 in London, until London goes forward in March.
            ['2026-11-03T16:00:00Z', '15:00', '16:00', 60, 'Africa/Cairo', '2027-03-28T01:00:00Z'],
        ])
        // The Saturday lessons fall after both changes: their hour does not move.
        ->and(ShiftNotice::query()->pluck('slot_id')->unique()->all())->toBe([lessonAt('2026-10-27 18:00')->slot_id])
        ->and(ShiftNotice::query()->orderBy('changes_at')->first()->lessons)->toBe(1)
        // A second look finds nothing new.
        ->and(app(PlanDstChanges::class)->handle())->toBe(0)
        ->and(ShiftNotice::query()->count())->toBe(2);
});

it('sees a student in Riyadh, who has no summer time, move with Cairo until spring', function () {
    $this->student->forceFill(['timezone' => 'Asia/Riyadh'])->save();

    app(PlanDstChanges::class)->handle();

    expect(notices())->toBe([
        ['2026-10-31T16:00:00Z', '18:00', '19:00', 60, 'Africa/Cairo', '2027-04-29T22:00:00Z'],
        ['2026-11-03T16:00:00Z', '18:00', '19:00', 60, 'Africa/Cairo', '2027-04-29T22:00:00Z'],
    ]);
});

it('tells the family 14 days and 2 days before, and not once the academy kept their hour', function () {
    app(PlanDstChanges::class)->handle();

    CarbonImmutable::setTestNow('2026-10-12 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(0);

    CarbonImmutable::setTestNow('2026-10-14 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(1)
        ->and(told($this->guardian, 'scheduling.dst_family'))->toBe(1)
        ->and(told($this->student, 'scheduling.dst_family'))->toBe(1)
        ->and(app(SendDstNotices::class)->handle())->toBe(0);

    CarbonImmutable::setTestNow('2026-10-21 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(1);        // 3 November comes within 14 days

    CarbonImmutable::setTestNow('2026-10-25 16:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(1)       // the late reminder for 27 October
        ->and(told($this->guardian, 'scheduling.dst_family'))->toBe(3);

    // The academy keeps their hour for 3 November instead: they hear that, and nothing more about it.
    $november = ShiftNotice::query()->where('changes_at', '2026-11-03 16:00:00+00')->firstOrFail();
    app(DecideDstChange::class)->handle($november, 'keep_student', $this->admin->id);

    CarbonImmutable::setTestNow('2026-11-02 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(0)
        ->and(told($this->guardian, 'scheduling.dst_kept_family'))->toBe(1);
});

it('keeps the student\'s hour by moving the teacher\'s for as long as the clocks differ, then puts it back', function () {
    app(PlanDstChanges::class)->handle();
    $october = ShiftNotice::query()->orderBy('changes_at')->firstOrFail();
    $before = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->count();

    app(DecideDstChange::class)->handle($october, 'keep_student', $this->admin->id);

    // 27 October at 19:00 in Cairo is still 16:00 in London; the week after, 18:00 in Cairo is 16:00 in London again.
    expect(lessonAt('2026-10-27 19:00')?->status)->toBe(SessionStatus::Scheduled)
        ->and(lessonAt('2026-10-27 18:00'))->toBeNull()
        ->and(lessonAt('2026-11-03 18:00')?->status)->toBe(SessionStatus::Scheduled)
        ->and(lessonAt('2026-10-27 19:00')->starts_at->setTimezone('Europe/London')->format('H:i'))->toBe('16:00')
        ->and(lessonAt('2026-11-03 18:00')->starts_at->setTimezone('Europe/London')->format('H:i'))->toBe('16:00')
        // The Tuesday weekly time: until the change, an hour later for two days, then back.
        ->and(RecurringSlot::query()->where('weekday', 2)->orderBy('effective')->get()->map(fn ($s) => [$s->status, substr($s->local_start, 0, 5), $s->effective])->all())->toBe([
            ['ended', '18:00', '[2026-10-06,2026-10-27)'],
            ['active', '19:00', '[2026-10-27,2026-10-29)'],
            ['active', '18:00', '[2026-10-29,)'],
        ])
        // Making the coming weeks again adds nothing; the November notice no longer happens.
        ->and(count(app(MaterializeSessions::class)->handle([$this->booked->enrollmentId])['created']))->toBe(0)
        ->and(Session::query()->where('enrollment_id', $this->booked->enrollmentId)->count())->toBe($before)
        ->and(ShiftNotice::query()->pluck('decision')->all())->toBe(['keep_student'])
        ->and(app(PlanDstChanges::class)->handle())->toBe(0)
        // The teacher hears their new time; the family's did not move, and they had not been told otherwise.
        ->and(told($this->teacher, 'scheduling.lessons_shifted_teacher'))->toBe(1)
        ->and(told($this->guardian, 'scheduling.lessons_shifted_family'))->toBe(0)
        ->and(told($this->guardian, 'scheduling.dst_kept_family'))->toBe(0)
        // Decided once and for all.
        ->and(fn () => app(DecideDstChange::class)->handle($october->refresh(), 'keep_teacher', $this->admin->id))->toThrow(ValidationException::class);
});

it('keeps the teacher\'s time when told to, and refuses to keep the student\'s when the teacher is off shift then', function () {
    $this->student->forceFill(['timezone' => 'Asia/Riyadh'])->save();
    app(PlanDstChanges::class)->handle();
    [$saturday, $tuesday] = ShiftNotice::query()->orderBy('changes_at')->get()->all();

    expect(app(DecideDstChange::class)->handle($saturday, 'keep_teacher', $this->admin->id)->decision)->toBe('keep_teacher');

    // Keeping 18:00 in Riyadh means 17:00 in Cairo: the shift starts at 17:00, so it fits. One more hour would not.
    DB::table('teacher_shift_rules')->where('teacher_id', $this->teacher->id)->update(['local_start' => '17:30', 'duration_min' => 210]);
    expect(fn () => app(DecideDstChange::class)->handle($tuesday, 'keep_student', $this->admin->id))->toThrow(ValidationException::class)
        ->and($tuesday->refresh()->decision)->toBe('pending')
        ->and(lessonAt('2026-11-03 18:00')?->status)->toBe(SessionStatus::Scheduled);
});

it('moves every weekly lesson of a teacher for some days, all or nothing, and tells everyone once', function () {
    $shift = app(ShiftTeacherLessons::class);
    $before = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->count();

    expect($shift->preview($this->teacher->id, '2026-11-01', '2026-11-14', 60))->toMatchArray(['lessons' => 4, 'students' => 1, 'problems' => []])
        // Two hours earlier is 16:00, before the shift starts.
        ->and(count($shift->preview($this->teacher->id, '2026-11-01', '2026-11-14', -120)['problems']))->toBe(4)
        ->and(fn () => $shift->handle($this->teacher->id, '2026-11-01', '2026-11-14', -120, $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => $shift->handle($this->teacher->id, '2026-10-01', '2026-10-14', 60, $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => $shift->handle($this->teacher->id, '2026-11-14', '2026-11-01', 60, $this->admin->id))->toThrow(ValidationException::class)
        ->and(lessonAt('2026-11-03 18:00'))->not->toBeNull();

    expect($shift->handle($this->teacher->id, '2026-11-01', '2026-11-14', 60, $this->admin->id))->toBe(4)
        ->and(collect(['2026-11-03 19:00', '2026-11-07 19:00', '2026-11-10 19:00', '2026-11-14 19:00', '2026-10-31 18:00', '2026-11-17 18:00', '2026-11-21 18:00'])
            ->map(fn (string $at) => lessonAt($at)?->status)->unique()->all())->toBe([SessionStatus::Scheduled])
        ->and(count(app(MaterializeSessions::class)->handle([$this->booked->enrollmentId])['created']))->toBe(0)
        ->and(Session::query()->where('enrollment_id', $this->booked->enrollmentId)->count())->toBe($before)
        ->and(RecurringSlot::query()->where('status', 'active')->count())->toBe(4)
        ->and(told($this->teacher, 'scheduling.teacher_lessons_shifted'))->toBe(1)
        ->and(told($this->teacher, 'scheduling.lessons_shifted_teacher'))->toBe(0)
        ->and(told($this->guardian, 'scheduling.lessons_shifted_family'))->toBe(2);
});

it('shows the coming changes in the back office, where the academy decides one or several, and moves a teacher\'s lessons', function () {
    app(PlanDstChanges::class)->handle();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    [$october, $november] = ShiftNotice::query()->orderBy('changes_at')->get()->all();

    Livewire::test(DstChanges::class)
        ->assertSee($this->student->name)
        ->assertSee(__('scheduling::dst.cause', ['city' => Timezones::city('Europe/London')]))
        ->assertSee(__('scheduling::dst.cause', ['city' => Timezones::city('Africa/Cairo')]))
        ->assertSee(__('scheduling::dst.possible'))
        ->callAction('keepTeacher', arguments: ['notice' => $november->id])
        ->callAction('keepStudent', arguments: ['notice' => $october->id])
        ->assertHasNoActionErrors()
        ->assertSee(__('scheduling::dst.decisions.keep_student'));

    expect($october->refresh()->decision)->toBe('keep_student')
        ->and(lessonAt('2026-10-27 19:00'))->not->toBeNull()
        ->and(DstChanges::getNavigationBadge())->toBeNull();

    Livewire::test(DstChanges::class)
        ->callAction('shift', data: ['teacher_id' => $this->teacher->id, 'from' => '2026-11-15', 'to' => '2026-11-22', 'minutes' => '30'])
        ->assertHasNoActionErrors();
    expect(lessonAt('2026-11-17 18:30'))->not->toBeNull();
});

it('tells the family on their lessons page when their hour is about to move', function () {
    app(PlanDstChanges::class)->handle();
    CarbonImmutable::setTestNow('2026-10-20 09:00 UTC');
    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');

    Livewire::test(FamilyLessons::class)
        ->assertSee(Timezones::city('Europe/London'))
        ->assertSeeText(CarbonImmutable::parse('2026-01-06 15:00')->locale(app()->getLocale())->translatedFormat('l H:i'));
});

it('keeps the clock changes screen to those who manage lessons', function () {
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/clock-changes')->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/admin/clock-changes')->assertForbidden();
});
