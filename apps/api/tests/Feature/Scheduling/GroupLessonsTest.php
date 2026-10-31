<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Credits\Models\CreditReservation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\TeacherProfile;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Actions\Courses\ResumeEnrollment;
use Modules\Scheduling\Actions\Groups\EndGroup;
use Modules\Scheduling\Actions\Groups\SaveGroupCapacity;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Contracts\Events\GroupStarted;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Queries\Groups\OpenGroups;
use Tests\Support\GroupLessons;
use Tests\Support\RivalConnection;

uses(GroupLessons::class, RivalConnection::class);

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a teacher who
 * takes groups, seats up to their most, a group that starts with its
 * students, one lesson a date with every member seated, and one student's
 * change never touching the others.
 */

beforeEach(fn () => $this->setUpGroups());

afterEach(fn () => CarbonImmutable::setTestNow());

$refusal = function (Closure $do): string {
    try {
        $do();
    } catch (ValidationException $e) {
        return (string) collect($e->errors())->flatten()->first();
    }

    return '';
};

$seated = fn (Session $s) => SessionParticipant::query()->where('session_id', $s->id)->where('role', 'student')->pluck('user_id')->sort()->values()->all();

it('keeps a group within its teacher\'s most, and a teacher\'s group size within 2–20', function () use ($refusal) {
    expect($refusal(fn () => $this->group(5)))->toBe(__('scheduling::groups.errors.over_teacher', ['max' => 4]))
        ->and($refusal(fn () => $this->group(1)))->toBe(__('scheduling::groups.errors.capacity_range'))
        ->and($refusal(fn () => $this->group(3, 4)))->toBe(__('scheduling::groups.errors.min_to_start', ['capacity' => 3]));

    $other = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', ['ar'], [$this->quran]));
    expect($refusal(fn () => $this->group(3, teacherId: $other->id)))->toBe(__('scheduling::groups.errors.no_groups'))
        ->and(app(PeopleApi::class)->groupCapacity($other->id))->toBeNull();

    expect($refusal(fn () => app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', acceptsGroups: true, maxGroupSize: 21))))
        ->toBe(__('people::people.errors.max_group'))
        ->and($refusal(fn () => app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($other->id, 'male', acceptsGroups: true))))
        ->toBe(__('people::people.errors.max_group'));

    // Saved without the group fields (an import, an older form), the profile keeps them.
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran]));
    expect(app(PeopleApi::class)->groupCapacity($this->teacher->id))->toBe(4);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran], acceptsGroups: false, maxGroupSize: 9));
    expect(TeacherProfile::query()->find($this->teacher->id)->max_group_size)->toBeNull();
});

it('books a group\'s weekly times as the teacher\'s, so they cannot overlap another of theirs', function () use ($refusal) {
    $group = $this->group();

    expect(RecurringSlot::query()->where('group_id', $group->id)->get())->toHaveCount(2)
        ->each(fn ($s) => $s->enrollment_id->toBeNull()->student_id->toBeNull())
        ->and($group->status)->toBe('forming')
        ->and($group->sessions_per_week)->toBe(2)
        ->and(Session::query()->count())->toBe(0)       // forming: no lessons yet
        ->and($refusal(fn () => $this->group()))->toBe(__('scheduling::find.errors.slot_taken'));
});

it('starts with its first student, makes one lesson a date with every member seated, and makes each date once', function () use ($seated) {
    Event::fake([GroupStarted::class]);
    $group = $this->group();
    [$a, $b] = [$this->student(), $this->student()];

    $first = $this->join($group, $a);
    expect($first->started)->toBeTrue()->and($first->seat)->toBe(1)->and($group->refresh()->status)->toBe('running')
        ->and($group->started_on->toDateString())->toBe('2026-10-05');
    Event::assertDispatched(GroupStarted::class, fn (GroupStarted $e) => $e->groupId === $group->id && $e->studentIds === [$a->id]);

    $second = $this->join($group, $b);
    expect($second->started)->toBeFalse()->and($second->seat)->toBe(2)->and($second->lessons)->toBe(16);

    $lessons = Session::query()->where('group_id', $group->id)->orderBy('starts_at')->get();
    expect($lessons)->toHaveCount(16)
        ->and($lessons->first()->starts_at->setTimezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-10-06 18:00')
        ->and($lessons->pluck('enrollment_id')->filter()->all())->toBe([])
        ->and($seated($lessons->first()))->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and(SessionParticipant::query()->where('session_id', $lessons->first()->id)->where('user_id', $b->id)->value('enrollment_id'))->toBe($second->enrollmentId);

    // A second run makes nothing new; each student has their own course with the group's teacher.
    expect(app(MaterializeSessions::class)->handle()['created'])->toBe([])
        ->and(Session::query()->where('group_id', $group->id)->count())->toBe(16)
        ->and(DB::table('enrollments')->where('id', $second->enrollmentId)->value('teacher_id'))->toBe($this->teacher->id);
});

it('waits as forming until it has the students it starts with', function () {
    Event::fake([GroupStarted::class]);
    $group = $this->group(3, 2);

    expect($this->join($group, $this->student())->started)->toBeFalse()
        ->and($group->refresh()->status)->toBe('forming')
        ->and(Session::query()->where('group_id', $group->id)->count())->toBe(0);
    Event::assertNotDispatched(GroupStarted::class);

    expect($this->join($group, $this->student())->started)->toBeTrue()
        ->and($group->refresh()->status)->toBe('running')
        ->and(SessionParticipant::query()->where('role', 'student')->count())->toBe(32);
    Event::assertDispatched(GroupStarted::class, fn (GroupStarted $e) => count($e->studentIds) === 2);
});

it('takes no one past the last seat, and lets one join at a time', function () use ($refusal) {
    $group = $this->group(2);
    $this->join($group, $this->student());

    // While one join holds the group, a second cannot take the group's lock: it waits for the first, then counts again.
    $locked = null;
    $this->rivalCommitsBefore('insert into "group_members"', function () use (&$locked, $group) {
        $locked = (bool) $this->rival()->selectOne('select pg_try_advisory_xact_lock(hashtext(?)) as ok', ['group:'.$group->id])->ok;
    });
    $this->join($group, $this->student());

    expect($locked)->toBeFalse()
        ->and($refusal(fn () => $this->join($group, $this->student())))->toBe(__('scheduling::groups.errors.full', ['capacity' => 2]))
        ->and(GroupMember::query()->count())->toBe(2)
        ->and(DB::table('enrollments')->count())->toBe(2);
});

it('changes seats within the teacher\'s most and never below the students in it', function () use ($refusal) {
    $group = $this->group(3);
    $this->join($group, $this->student());
    $this->join($group, $this->student());

    expect($refusal(fn () => app(SaveGroupCapacity::class)->handle($group->id, 1, $this->admin->id)))->toBe(__('scheduling::groups.errors.capacity_range'))
        ->and($refusal(fn () => app(SaveGroupCapacity::class)->handle($group->id, 5, $this->admin->id)))->toBe(__('scheduling::groups.errors.over_teacher', ['max' => 4]));

    app(SaveGroupCapacity::class)->handle($group->id, 4, $this->admin->id);
    expect($group->refresh()->capacity)->toBe(4);

    $this->join($group, $this->student());
    expect($refusal(fn () => app(SaveGroupCapacity::class)->handle($group->id, 2, $this->admin->id)))->toBe(__('scheduling::groups.errors.below_members', ['count' => 3]));
});

it('seats a student joining a running group in its lessons from now on only', function () use ($seated) {
    $group = $this->group();
    $this->join($group, $this->student());
    [$first, $second] = Session::query()->where('group_id', $group->id)->orderBy('starts_at')->take(2)->get()->all();

    CarbonImmutable::setTestNow($first->ends_at->addHour());
    $late = $this->student();
    $joined = $this->join($group, $late);

    expect($seated($first))->not->toContain($late->id)
        ->and($seated($second))->toContain($late->id)
        ->and($joined->lessons)->toBe(16);       // the 15 left, and the date the horizon now reaches
});

it('takes a paused or ended student out of their own group lessons only, never the lesson', function () use ($seated) {
    $group = $this->group();
    [$a, $b] = [$this->student(), $this->student()];
    $ja = $this->join($group, $a);
    $this->join($group, $b);
    $this->pay($ja);

    $lessons = fn () => Session::query()->where('group_id', $group->id)->orderBy('starts_at')->get();
    expect(CreditReservation::query()->where('status', 'held')->count())->toBe(8);

    // Paused the second week (13–19 October): out of those two lessons, their minutes back; B and the lessons stay.
    app(PauseEnrollment::class)->handle($ja->enrollmentId, '2026-10-13', '2026-10-19', 'travel', $this->admin->id);
    $all = $lessons();
    expect($all->every(fn (Session $s) => $s->status === SessionStatus::Scheduled))->toBeTrue()
        ->and($seated($all[2]))->toBe([$b->id])->and($seated($all[3]))->toBe([$b->id])
        ->and($seated($all[0]))->toContain($a->id)->and($seated($all[4]))->toContain($a->id)
        ->and(CreditReservation::query()->where('status', 'held')->count())->toBe(8);   // two came back and funded the next ones

    // Back on: seated again in the lessons ahead.
    app(ResumeEnrollment::class)->handle($ja->enrollmentId, $this->admin->id);
    expect($seated($lessons()[2]))->toContain($a->id);

    // Ended from 1 November: out of the group's lessons from then, the seat free from that day, B still in all of them.
    app(EndEnrollment::class)->handle($ja->enrollmentId, '2026-11-01', 'moved away', $this->admin->id);
    $after = $lessons()->filter(fn (Session $s) => $s->starts_at->greaterThanOrEqualTo(CarbonImmutable::parse('2026-11-01', 'Africa/Cairo')));
    expect($after->every(fn (Session $s) => $seated($s) === [$b->id]))->toBeTrue()
        ->and($lessons()->every(fn (Session $s) => in_array($b->id, $seated($s), true)))->toBeTrue()
        ->and(GroupMember::query()->where('enrollment_id', $ja->enrollmentId)->first()->left_on->toDateString())->toBe('2026-11-01');
});

it('ends a group: every course ends, its lessons left are cancelled, nothing stays held', function () {
    $group = $this->group();
    $joined = $this->join($group, $this->student());
    $this->join($group, $this->student());
    $this->pay($joined);

    $done = app(EndGroup::class)->handle($group->id, 'Not enough students', $this->admin->id);

    expect($done['courses'])->toBe(2)
        ->and($group->refresh()->status)->toBe(LessonGroup::ENDED)
        ->and(Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->count())->toBe(0)
        ->and(CreditReservation::query()->where('status', 'held')->count())->toBe(0)
        ->and(DB::table('enrollments')->where('status', 'ended')->count())->toBe(2)
        ->and(RecurringSlot::query()->where('group_id', $group->id)->where('status', 'active')->count())->toBe(0)
        ->and(GroupMember::query()->whereNull('left_on')->count())->toBe(0);
});

it('lists open groups with free seats, their times on the student\'s clock and the lessons held', function () {
    $group = $this->group(2);
    $full = $this->group(2, teacherId: tap(User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Asia/Riyadh']), fn ($t) => app(PeopleApi::class)
        ->saveTeacherProfile(new TeacherProfileData($t->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 2)))->id);
    $this->join($group, $this->student());
    $this->join($full, $this->student());
    $this->join($full, $this->student());

    $open = app(OpenGroups::class)->handle($this->quran, studentTimezone: 'Europe/London');
    expect($open)->toHaveCount(1)
        ->and($open[0]['id'])->toBe($group->id)
        ->and($open[0]['free'])->toBe(1)
        ->and($open[0]['teacher_name'])->toBe($this->teacher->name)
        ->and($open[0]['times'])->toBe([['weekday' => 2, 'start' => '16:00'], ['weekday' => 6, 'start' => '16:00']])   // 18:00 Cairo is 16:00 in London (BST)
        ->and($open[0]['held'])->toBe(0)
        ->and($open[0]['next']['timezone'])->toBe('Europe/London')
        ->and($open[0])->not->toHaveKeys(['teacher_email', 'teacher_phone']);
});

it('keeps groups to their own academy', function () {
    $group = $this->group();
    $this->join($group, $this->student());

    $this->actAsTenant($this->createAcademy(['slug' => 'other']));
    expect(LessonGroup::query()->count())->toBe(0)
        ->and(GroupMember::query()->count())->toBe(0)
        ->and(LessonGroup::query()->find($group->id))->toBeNull()
        ->and(SchedulingSettings::current()->late_join)->toBe('discount');
});
