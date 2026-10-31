<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Classroom\Recordings\RecordingRules;
use Modules\Evaluation\Queries\PerformanceEvents;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\Groups\ReassignGroupTeacher;
use Modules\Scheduling\Actions\ReassignTeacher;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Http\V1\Resources\LessonPresenter;
use Modules\Scheduling\Queries\Calendar\CalendarFilter;
use Modules\Scheduling\Queries\Calendar\LessonsCalendar;
use Modules\Scheduling\Queries\LessonList;
use Modules\Scheduling\Queries\LiveBoardQuery;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * A group lesson on every surface (docs/architecture/02, "الحصص الجماعية"):
 * one row a lesson with its students on the classroom's card, the board,
 * the calendar and the teacher's KPIs; classmates as first names only (the
 * kids' look, a number); the lesson moved or its teacher changed only for
 * the whole group, by the academy.
 */

beforeEach(function () {
    $this->setUpGroups();
    $this->group = $this->group();
    $this->kids = [];
    foreach (['Amira Hassan', 'Badr Salem', 'Dina Khalil'] as $name) {
        $s = $this->student();
        $s->forceFill(['name' => $name])->save();
        $this->kids[] = $s;
        $this->pay($this->join($this->group, $s));
    }
    [$this->a, $this->b, $this->c] = $this->kids;
    $this->lesson = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** On shift Tuesdays and Saturdays, 17:00–21:00 Cairo. */
$shifts = function (User $teacher) {
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $teacher->id, 'weekday' => $weekday, 'local_start' => '17:00', 'duration_min' => 240]);
    }
};

it('puts every student on the classroom\'s card, and seats each of them and their guardians', function () {
    $card = app(AttendanceApi::class)->lesson($this->lesson->id);
    expect($card->groupId)->toBe($this->group->id)
        ->and($card->capacity)->toBe(3)
        ->and(array_column($card->students, 'name'))->toBe(['Amira Hassan', 'Badr Salem', 'Dina Khalil'])
        ->and(array_column($card->students, 'first_name'))->toBe(['Amira', 'Badr', 'Dina'])
        ->and($card->studentId)->toBe($this->a->id)           // the first, for the one-to-one callers
        ->and(app(AttendanceApi::class)->lessonsOf($this->teacher->id, $this->lesson->starts_at, $this->lesson->ends_at))->toHaveCount(1);

    $access = app(ClassroomAccess::class);
    $seat = $access->seatFor($this->b->id, $card);
    expect([$seat->identity, $seat->role, $seat->name])->toBe([$this->b->id, 'student', 'Badr Salem'])
        ->and($access->seatFor($this->payer->id, $card)?->role)->toBe('student')            // a guardian comes in as their child
        ->and($access->seatFor(User::factory()->withRole(Role::Student)->create()->id, $card))->toBeNull();

    // Recording needs everyone in the room: the teacher and each of the three.
    expect(collect(app(RecordingRules::class)->people($card))->pluck('role')->countBy()->all())->toBe(['teacher' => 1, 'student' => 3]);
});

it('shows a group lesson once on the calendar, the board and in the teacher\'s numbers', function () {
    $from = $this->lesson->starts_at->subHour();
    $to = $this->lesson->ends_at->addHour();
    $calendar = app(LessonsCalendar::class)->lessons(new CalendarFilter, $from, $to);
    expect($calendar)->toHaveCount(1)
        ->and($calendar[0]->students)->toBe(3)
        ->and($calendar[0]->student)->toBe(__('scheduling::group_outcomes.label', ['count' => 3]))
        ->and(app(LessonsCalendar::class)->byStatus(new CalendarFilter, $from, $to)['scheduled']['count'])->toBe(1);

    $board = app(LiveBoardQuery::class)->around($this->lesson->starts_at, 60, 'UTC');
    expect(collect($board)->where('id', $this->lesson->id))->toHaveCount(1)
        ->and(collect($board)->firstWhere('id', $this->lesson->id)->students)->toHaveCount(3);

    $api = app(AttendanceApi::class);
    foreach ([$this->teacher, $this->a, $this->b, $this->c] as $u) {
        $api->participantJoined($this->lesson->id, $u->id, $this->lesson->starts_at);
    }
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    $kpis = app(TeacherKpis::class)->forTeachers([$this->teacher->id], $this->lesson->ends_at->addDay())[$this->teacher->id];
    expect($kpis['punctuality']['n'])->toBe(1)->and($kpis['taught']['n'])->toBe(1)
        ->and(app(LessonList::class)->forTeacher($this->teacher->id, $from, $to))->toHaveCount(1);
    expect(collect(app(PerformanceEvents::class)->forTeacher($this->teacher->id, 30, $this->lesson->ends_at->addDays(3)))->where('session_id', $this->lesson->id)->count())
        ->toBeLessThanOrEqual(1);
});

it('tells a family its seat and its classmates\' first names only; the kids\' look only how many', function () {
    $row = collect(app(LessonList::class)->forStudent($this->a->id, $this->lesson->starts_at, $this->lesson->ends_at))->firstWhere('id', $this->lesson->id);
    $present = app(LessonPresenter::class);

    $family = $present->lesson($row, CarbonImmutable::now(), 'teacher', true);
    expect($family['format'])->toBe('group')
        ->and($family['group'])->toMatchArray(['capacity' => 3, 'seats_taken' => 3, 'classmates' => ['Badr', 'Dina'], 'classmates_count' => 2, 'students' => null])
        ->and(json_encode($family))->not->toContain('Salem')->not->toContain('Khalil')->not->toContain((string) $this->b->email);

    $kid = $present->lesson($row, CarbonImmutable::now(), 'teacher', false, kids: true);
    expect($kid['group']['classmates'])->toBeNull()
        ->and($kid['group']['classmates_count'])->toBe(2)
        ->and($kid['group']['classmates_label'])->toBe(trans_choice('scheduling::group_outcomes.with_classmates', 2, ['count' => 2]))
        ->and(json_encode($kid))->not->toContain('Badr');

    $teacherRow = collect(app(LessonList::class)->forTeacher($this->teacher->id, $this->lesson->starts_at, $this->lesson->ends_at))->first();
    $teacher = $present->lesson($teacherRow, CarbonImmutable::now(), 'student');
    expect($teacher['with'])->toBe(__('scheduling::group_outcomes.label', ['count' => 3]))
        ->and(array_column($teacher['group']['students'], 'name'))->toBe(['Amira Hassan', 'Badr Salem', 'Dina Khalil'])
        ->and($teacher['group']['classmates'])->toBeNull();

    // A private lesson says so.
    expect(app(LessonPresenter::class)->lesson((object) (['group_id' => null] + (array) $row), CarbonImmutable::now(), 'teacher', true)['format'])->toBe('private');
});

it('moves a group lesson only for the whole group, by the academy', function () use ($shifts) {
    $shifts($this->teacher);
    $newStart = $this->lesson->starts_at->addHours(2);
    expect(fn () => app(RescheduleSession::class)->handle($this->lesson, $newStart, 'student', $this->payer->id))
        ->toThrow(ValidationException::class, __('scheduling::group_outcomes.errors.whole_group'));

    app(RescheduleSession::class)->handle($this->lesson, $newStart, 'academy', $this->admin->id);

    $periods = SessionParticipant::query()->where('session_id', $this->lesson->id)->pluck('block_period')->unique();
    expect($this->lesson->refresh()->starts_at->equalTo($newStart))->toBeTrue()
        ->and($periods)->toHaveCount(1)
        ->and(SessionParticipant::query()->where('session_id', $this->lesson->id)->where('role', 'student')->count())->toBe(3);
});

it('changes a group\'s teacher only for the whole group, to one who takes groups of its size', function () use ($shifts) {
    $enrollment = (string) DB::table('group_members')->where('student_id', $this->a->id)->value('enrollment_id');
    $other = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $shifts($other);
    expect(fn () => app(ReassignTeacher::class)->handle($enrollment, $other->id, CarbonImmutable::parse('2026-10-10'), $this->admin->id))
        ->toThrow(ValidationException::class, __('scheduling::group_outcomes.errors.whole_group'));

    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', ['ar'], [$this->quran]));
    expect(fn () => app(ReassignGroupTeacher::class)->handle($this->group->id, $other->id, CarbonImmutable::parse('2026-10-10'), $this->admin->id))
        ->toThrow(ValidationException::class, __('scheduling::group_outcomes.errors.teacher_no_groups'));

    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 2));
    expect(fn () => app(ReassignGroupTeacher::class)->handle($this->group->id, $other->id, CarbonImmutable::parse('2026-10-10'), $this->admin->id))
        ->toThrow(ValidationException::class, __('scheduling::group_outcomes.errors.teacher_too_small', ['max' => 2, 'count' => 3]));

    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 5));
    $done = app(ReassignGroupTeacher::class)->handle($this->group->id, $other->id, CarbonImmutable::parse('2026-10-10'), $this->admin->id);

    $after = Session::query()->where('group_id', $this->group->id)->where('starts_at', '>=', CarbonImmutable::parse('2026-10-10', 'Africa/Cairo'))->get();
    expect($done['moved'])->toBe($after->count())
        ->and($after->pluck('teacher_id')->unique()->all())->toBe([$other->id])
        ->and($this->lesson->refresh()->teacher_id)->toBe($this->teacher->id)          // before the day: the first teacher's
        ->and(LessonGroup::query()->find($this->group->id)->teacher_id)->toBe($other->id)
        ->and(DB::table('enrollments')->whereIn('id', DB::table('group_members')->where('group_id', $this->group->id)->pluck('enrollment_id'))->pluck('teacher_id')->unique()->all())->toBe([$other->id])
        ->and(SessionParticipant::query()->where('session_id', $after->first()->id)->where('role', 'teacher')->value('user_id'))->toBe($other->id);
});
