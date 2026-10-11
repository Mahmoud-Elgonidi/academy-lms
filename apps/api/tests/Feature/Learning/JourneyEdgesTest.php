<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\OpenWeeklyChest;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Domain\Journey\LessonStars;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\Learning\Queries\Journey\StarsQuery;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Games\GameCatalog;
use Tests\Support\EnrolledStudent;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, StarterGames::class);

/*
 * The journey at its edges (docs/architecture/02, section 18), over each
 * academy's own curriculum and nothing tied to a subject: a course with no
 * published curriculum, one level or thirty, every lesson mastered, nothing
 * taught yet, three courses in three subjects, a guardian with three
 * children, every look and style, lessons that went every way, the week's
 * chest turning over at Saturday midnight on the learner's own clock, and a
 * bounded number of queries however long the curriculum. The fixture's
 * student is in London; lessons are Tuesdays and Saturdays, 18:00 Cairo,
 * from 6 October 2026.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->installStarterGames();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    app(PeopleApi::class)->saveStudentProfile($this->student->id, null, LearnerStage::Kids);
    app()->forgetInstance(FamilyView::class);
    $this->lessonsOf = fn (string $enrollmentId) => Session::query()->where('enrollment_id', $enrollmentId)->orderBy('starts_at')->get();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A published program of $levels levels with $perLevel lessons each (straight in the level); its lesson ids in order. */
function jeProgram(string $subjectId, string $name, int $levels, int $perLevel, bool $publish = true): array
{
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => $subjectId, 'name_ar' => $name, 'name_en' => $name]);
    $version = $program->workingVersion();
    $save = app(SaveNode::class);
    $lessons = [];
    for ($l = 1; $l <= $levels; $l++) {
        $level = $save->handle($version, null, ['kind' => 'level', 'title_ar' => "{$name} {$l}", 'title_en' => "{$name} {$l}"]);
        for ($i = 1; $i <= $perLevel; $i++) {
            $lessons[] = $save->handle($version, null, ['kind' => 'lesson', 'parent_id' => $level->id, 'title_ar' => "{$name} {$l}.{$i}", 'title_en' => "{$name} {$l}.{$i}"])->id;
        }
    }
    if ($publish) {
        app(PublishVersion::class)->handle($version, null);
    }

    return [$program->id, $lessons];
}

/** Another course for a student, in a subject of its own, following a program when one is given. */
function jeCourse(User $student, string $subject, ?callable $program = null): array
{
    $subjectId = app(CatalogApi::class)->createSubject($subject, $subject);
    [$programId, $lessons] = $program === null ? [null, []] : $program($subjectId);
    $enrollment = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $subjectId, test()->teacher->id, CarbonImmutable::now()));
    if ($programId !== null) {
        app(EnrollmentApi::class)->attachCurriculum($enrollment, $programId);
    }

    return [$enrollment, $lessons];
}

function jeToken(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

/** A lesson took place: the student came in $late minutes after the start and left $left minutes after it (null: stayed). */
function jeLesson(Session $lesson, string $outcome, ?int $late = null, ?int $left = null): void
{
    if ($outcome === 'completed' && $late !== null) {
        app(AttendanceApi::class)->participantJoined($lesson->id, test()->student->id, $lesson->starts_at->addMinutes($late));
        if ($left !== null) {
            app(AttendanceApi::class)->participantLeft($lesson->id, test()->student->id, $lesson->starts_at->addMinutes($left));
        }
    }
    $lesson->update(['status' => $outcome]);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, $outcome, []));
}

/** The queries one read of the journey takes, as a request makes it (nothing kept from an earlier one). */
function jeQueries(callable $read): int
{
    app()->forgetScopedInstances();
    test()->actAsTenant(test()->academy);
    $count = 0;
    $on = true;
    DB::listen(function (QueryExecuted $q) use (&$count, &$on) {
        $count += $on ? 1 : 0;
    });
    $read();
    $on = false;

    return $count;
}

it('keeps a course with no published curriculum to its missions, beside one that has islands', function () {
    // The Quran course follows no program; English has only a draft.
    [$english] = jeCourse($this->student, 'English', fn (string $s) => [null, jeProgram($s, 'Draft', 2, 3, publish: false)[1]]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:55', 'UTC'));

    $data = $this->withToken(jeToken($this->student))->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/learning/journey/map')->assertOk()->json('data');
    $courses = collect($data['courses'])->keyBy('enrollment_id');
    expect($courses->keys()->sort()->values()->all())->toBe(collect([$this->booked->enrollmentId, $english])->sort()->values()->all());
    foreach ($courses as $course) {
        expect($course)->toMatchArray(['has_curriculum' => false, 'islands' => [], 'percent' => null, 'finished' => false, 'current' => null]);
    }
    // The lesson coming up is still a mission, on no station.
    $lesson = collect($data['missions'])->firstWhere('kind', 'lesson');
    expect($lesson)->toMatchArray(['enrollment_id' => $this->booked->enrollmentId, 'station_id' => null, 'island_id' => null, 'title' => 'Quran'])
        ->and($data['games']['egg']['have'])->toBe(0);
});

it('draws one level as one island, and thirty levels as the world\'s islands in turn', function () {
    [$programId, $short] = jeProgram($this->quran, 'Short', 1, 3);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $programId);
    [$long] = jeCourse($this->student, 'Maths', fn (string $s) => jeProgram($s, 'Long', 30, 1));

    $courses = collect(app(JourneyMap::class)->for($this->student->id, 'kids')['courses'])->keyBy('enrollment_id');
    $one = $courses[$this->booked->enrollmentId]['islands'];
    expect($one)->toHaveCount(1)
        ->and($one[0])->toMatchArray(['index' => 1, 'state' => 'here', 'lessons' => 3, 'done' => 0])
        ->and(array_column($one[0]['stations'], 'state'))->toBe(['here', 'todo', 'todo'])
        ->and(array_column($one[0]['stations'], 'playable'))->toBe([true, false, false]);

    $world = array_map(fn ($i) => $i->key, app(GameCatalog::class)->islands());
    $thirty = $courses[$long]['islands'];
    expect($thirty)->toHaveCount(30)
        ->and(array_column($thirty, 'index'))->toBe(range(1, 30))
        ->and(array_map(fn (array $i) => $i['art']['key'], $thirty))->toBe(array_map(fn (int $i) => $world[$i % count($world)], range(0, 29)))
        ->and(array_count_values(array_column($thirty, 'state')))->toBe(['here' => 1, 'ahead' => 29]);
});

it('marks every island done once every lesson is mastered, and keeps all of them to practise', function () {
    [$programId, $lessons] = jeProgram($this->quran, 'Juz Amma', 2, 3);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $programId);
    $tuesday = ($this->lessonsOf)($this->booked->enrollmentId)->first();
    jeLesson($tuesday, 'completed', 0);
    CarbonImmutable::setTestNow($tuesday->starts_at->addHour());
    app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $tuesday->id)->firstOrFail(), $this->teacher->id, [
        'engagement' => 5, 'overall' => 5, 'items' => array_map(fn (string $id) => ['node_id' => $id, 'outcome' => 'mastered', 'score' => 5], $lessons),
    ]);

    $map = app(JourneyMap::class)->for($this->student->id, 'kids');
    $course = $map['courses'][0];
    $stations = collect($course['islands'])->flatMap(fn (array $i) => $i['stations']);
    expect($course)->toMatchArray(['finished' => true, 'percent' => 100, 'current' => null, 'finish_on' => null])
        ->and(array_column($course['islands'], 'state'))->toBe(['done', 'done'])
        ->and($stations->pluck('state')->unique()->values()->all())->toBe(['done'])
        ->and($stations->every(fn (array $s) => $s['playable']))->toBeTrue()
        ->and($map['stars']['from']['mastered'])->toBe(6 * 5)
        // The next lesson stands on no station: nothing is left to learn.
        ->and(collect($map['missions'])->firstWhere('kind', 'lesson'))->toMatchArray(['station_id' => null]);
});

it('gives a student with nothing taught yet a whole journey: the first lesson to start on, no stars, no chest', function () {
    $newcomer = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Dubai']);
    app(PeopleApi::class)->saveStudentProfile($newcomer->id, null, LearnerStage::Middle);
    jeCourse($newcomer, 'Arabic', fn (string $s) => jeProgram($s, 'Arabic', 2, 2));

    $map = app(JourneyMap::class)->for($newcomer->id, 'middle');
    expect($map['stars'])->toMatchArray(['total' => 0])->and($map['stars']['rank']['key'])->toBe('beginner')
        ->and($map)->toMatchArray(['streak' => 0, 'last_lesson' => null, 'missions' => [], 'chest' => null, 'stickers' => null])
        ->and($map['courses'][0]['islands'][0]['stations'][0])->toMatchArray(['state' => 'here', 'playable' => true])
        ->and($map['courses'][0]['islands'][1]['state'])->toBe('ahead');
});

it('reads three courses of sixty lessons each in a bounded number of queries, however many homework missions there are', function () {
    $this->actAsTenant($this->academy);
    [$programId, $quran] = jeProgram($this->quran, 'Quran', 4, 15);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $programId);
    foreach (['English', 'Maths'] as $subject) {
        jeCourse($this->student, $subject, fn (string $s) => jeProgram($s, $subject, 4, 15));
    }
    foreach (array_slice($quran, 0, 4) as $id) {
        app(SaveNodeItems::class)->handle(Node::query()->findOrFail($id), [
            ['kind' => 'choice', 'prompt' => 'One?', 'options' => ['1', '2'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'Two?', 'options' => ['2', '1'], 'answer' => 0],
            ['kind' => 'choice', 'prompt' => 'Three?', 'options' => ['3', '1'], 'answer' => 0],
        ], null);
    }
    $lessons = ($this->lessonsOf)($this->booked->enrollmentId);
    $due = CarbonImmutable::parse('2026-10-20 18:00', 'UTC');
    foreach ([0, 1, 2, 3] as $n) {
        jeLesson($lessons[$n], 'completed', 1);
        CarbonImmutable::setTestNow($lessons[$n]->starts_at->addHour());
        app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $lessons[$n]->id)->firstOrFail(), $this->teacher->id, [
            'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $quran[$n], 'outcome' => 'mastered', 'score' => 5]],
            // Each due on Tuesday 20 October.
            'homework' => ['title' => "Homework {$n}", 'accepts' => 'text', 'due_days' => (int) ceil(CarbonImmutable::now()->diffInDays($due))],
        ]);
    }
    $read = fn () => app(JourneyMap::class)->for($this->student->id, 'kids');

    // Four homework missions, each on its report's lesson, read as one.
    $four = jeQueries($read);
    $missions = collect($read()['missions'])->where('kind', 'homework');
    expect($missions)->toHaveCount(4)->and($missions->pluck('station_id')->all())->toBe(array_slice($quran, 0, 4));
    Homework::query()->where('title', '!=', 'Homework 0')->delete();
    $one = jeQueries($read);

    // Measured on this fixture: 78 with four homework and 75 with one before (a read of its report's lessons for each homework, and
    // the lessons a week read twice per course), 69 for either after.
    expect($four)->toBe($one)->and($four)->toBeLessThanOrEqual(70);
});

it('shows a guardian of three children each child\'s own journey, and nobody else\'s', function () {
    $children = [$this->student];
    foreach (['Lina' => 'Maths', 'Omar' => 'Science'] as $name => $subject) {
        $child = User::factory()->withRole(Role::Student)->create(['name' => $name, 'timezone' => 'Europe/London']);
        app(PeopleApi::class)->saveStudentProfile($child->id, null, LearnerStage::Kids);
        app(PeopleApi::class)->linkGuardian($this->guardian->id, $child->id);
        jeCourse($child, $subject, fn (string $s) => jeProgram($s, $subject, 1, 2));
        $children[] = $child;
    }
    $token = jeToken($this->guardian);

    $subjects = [];
    foreach ($children as $child) {
        $data = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/learning/journey/map?learner_id='.$child->id)
            ->assertOk()->assertJsonPath('data.learner_id', $child->id)->assertJsonPath('data.viewer', 'guardian')->json('data');
        $subjects[] = array_column($data['courses'], 'subject');
        app('auth')->forgetGuards();
        app()->forgetInstance(FamilyView::class);
    }
    expect($subjects)->toBe([['Quran'], ['Maths'], ['Science']]);

    // Another family's child.
    $this->actAsTenant($this->academy);
    $stranger = User::factory()->withRole(Role::Student)->create();
    $this->withToken($token)->getJson('/api/v1/learning/journey/map?learner_id='.$stranger->id)->assertNotFound();
});

it('keeps the journey whole in every look and style, with games only where one fits the look', function () {
    $this->actAsTenant($this->academy);
    $learners = [];
    foreach ([LearnerStage::Kids, LearnerStage::Middle, LearnerStage::Teen, LearnerStage::Classic] as $stage) {
        $this->actAsTenant($this->academy);
        $learner = User::factory()->withRole(Role::Student)->create(['timezone' => 'Africa/Cairo']);
        app(PeopleApi::class)->saveStudentProfile($learner->id, CarbonImmutable::parse('2004-05-01'), $stage);
        jeCourse($learner, 'Course '.$stage->value, fn (string $s) => jeProgram($s, $stage->value, 2, 2));
        $learners[$stage->value] = jeToken($learner);
    }
    $shape = null;
    foreach (['playful', 'calm', 'plain'] as $style) {
        $this->actAsTenant($this->academy);
        app(SaveJourneyStyle::class)->handle($style);
        $this->clearTenant();
        foreach ($learners as $stage => $token) {
            app('auth')->forgetGuards();
            $data = $this->withToken($token)->getJson('/api/v1/learning/journey/map')->assertOk()->json('data');
            $shape ??= array_keys($data);
            expect(array_keys($data))->toBe($shape)
                ->and($data)->toMatchArray(['stage' => $stage, 'style' => $style])
                ->and($data['courses'][0]['islands'])->toHaveCount(2)
                // No starter game is made for adults; the plain style has none at all.
                ->and($data['games'] === null)->toBe($style === 'plain' || $stage === 'classic')
                ->and($data['stickers'] !== null)->toBe($stage === 'kids' && $style !== 'plain')
                ->and($data['stars']['total'])->toBe(0);
        }
    }
});

it('counts each lesson\'s stars as it went: late, left early, missed, cancelled, the teacher away, under review', function () {
    $lessons = ($this->lessonsOf)($this->booked->enrollmentId);
    jeLesson($lessons[0], 'completed', 7);                  // Tue 6 Oct: a little late, stayed: 3
    jeLesson($lessons[1], 'student_absent');                // Sat 10 Oct: missed: 0
    jeLesson($lessons[2], 'cancelled');                     // Tue 13 Oct
    jeLesson($lessons[3], 'teacher_absent');                // Sat 17 Oct
    jeLesson($lessons[4], 'completed', 15, 16);             // Tue 20 Oct: late, left early: 1
    $lessons[5]->update(['status' => 'needs_review']);      // Sat 24 Oct: disputed
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 12:00', 'UTC'));

    $map = app(JourneyMap::class)->for($this->student->id, 'kids');
    expect($map['stars']['from']['lessons'])->toBe(4)
        ->and($map['streak'])->toBe(1)                      // the missed lesson ends it; cancelled ones and the teacher's absence do not
        ->and($map['last_lesson'])->toMatchArray(['id' => $lessons[4]->id, 'stars' => 1, 'late_min' => 15, 'on_time' => false, 'stayed' => false]);

    // Week by week (each done chest opened as it comes): the missed lesson misses its week's chest; a cancelled
    // lesson and the teacher's absence ask nothing; a lesson under review waits for its decision.
    $chest = fn (string $utc) => app(JourneyMap::class)->for($this->student->id, 'kids', false, CarbonImmutable::parse($utc, 'UTC'))['chest'];
    $open = fn (string $period, string $utc) => app(OpenWeeklyChest::class)->handle($this->student->id, 'kids', $period, CarbonImmutable::parse($utc, 'UTC'));
    expect($chest('2026-10-14 12:00'))->toMatchArray(['period' => '2026-10-03', 'target' => 1, 'progress' => 1, 'state' => 'done']);
    $open('2026-10-03', '2026-10-14 12:00');
    expect($chest('2026-10-14 12:00'))->toMatchArray(['period' => '2026-10-10', 'target' => 1, 'progress' => 0, 'state' => 'missed'])
        ->and($chest('2026-10-19 12:00'))->toMatchArray(['period' => '2026-10-17', 'target' => 1, 'progress' => 1, 'state' => 'done']);
    $open('2026-10-17', '2026-10-25 12:00');
    expect($chest('2026-10-25 12:00'))->toMatchArray(['period' => '2026-10-24', 'target' => 2, 'progress' => 0, 'state' => 'active']);

    // Each counted the way LessonStars says.
    expect(collect(app(StarsQuery::class)->for($this->student->id)['by_lesson'])->map(fn (array $l) => $l['stars'])->all())
        ->toBe([$lessons[0]->id => 3, $lessons[1]->id => 0, $lessons[4]->id => 1]);
});

it('turns the week\'s chest over at Saturday midnight on the learner\'s own clock, and opens last week\'s first', function () {
    $lessons = ($this->lessonsOf)($this->booked->enrollmentId);
    jeLesson($lessons[0], 'completed', 1);                  // Tue 6 Oct: the week of Saturday 3 October is done
    $chest = fn (string $utc) => app(JourneyMap::class)->for($this->student->id, 'kids', false, CarbonImmutable::parse($utc, 'UTC'))['chest'];

    // London is on summer time: Saturday 10 October starts at 23:00 UTC on Friday.
    expect($chest('2026-10-09 22:59'))->toMatchArray(['period' => '2026-10-03', 'state' => 'done'])
        ->and($chest('2026-10-09 22:59')['ends_at']->equalTo(CarbonImmutable::parse('2026-10-09 23:00', 'UTC')))->toBeTrue()
        // A new week, and last week's chest, ready and not opened, still comes first.
        ->and($chest('2026-10-09 23:00'))->toMatchArray(['period' => '2026-10-03', 'state' => 'done']);

    app(OpenWeeklyChest::class)->handle($this->student->id, 'kids', '2026-10-03', CarbonImmutable::parse('2026-10-09 23:00', 'UTC'));
    expect($chest('2026-10-09 23:00'))->toMatchArray(['period' => '2026-10-10', 'target' => 2, 'progress' => 0, 'state' => 'active']);

    // In Los Angeles the same Saturday starts seven hours after midnight UTC.
    $this->actAsTenant($this->academy);
    DB::table('users')->where('id', $this->student->id)->update(['timezone' => 'America/Los_Angeles']);
    expect($chest('2026-10-10 06:59')['period'])->toBe('2026-10-03')
        ->and($chest('2026-10-10 07:00')['period'])->toBe('2026-10-10');

    // The week the clocks go back in London is 169 hours long; the next one starts at midnight GMT.
    DB::table('users')->where('id', $this->student->id)->update(['timezone' => 'Europe/London']);
    $late = $chest('2026-10-30 23:59');
    expect($late['period'])->toBe('2026-10-24')
        ->and($late['ends_at']->equalTo(CarbonImmutable::parse('2026-10-31 00:00', 'UTC')))->toBeTrue()
        ->and($chest('2026-10-31 00:00')['period'])->toBe('2026-10-31');
});

it('counts every lesson the student ever had, a trial before the course too, read through their own participant index', function () {
    $this->actAsTenant($this->academy);
    $lessons = ($this->lessonsOf)($this->booked->enrollmentId);
    // Years of lessons: moved back in time, one of them the trial before the course began.
    $past = [
        [$lessons[0], '2022-12-01 15:00', 0, null],         // the trial, before any enrolment
        [$lessons[1], '2023-01-10 15:00', 2, null],
        [$lessons[2], '2024-06-01 15:00', 12, 20],
    ];
    foreach ($past as [$lesson, $at, $late, $left]) {
        $start = CarbonImmutable::parse($at, 'UTC');
        $lesson->update(['starts_at' => $start, 'ends_at' => $start->addMinutes(30), 'status' => 'completed']);
        DB::table('session_participants')->where('session_id', $lesson->id)->where('user_id', $this->student->id)->update([
            'first_join_at' => $start->addMinutes($late), 'last_left_at' => $left === null ? null : $start->addMinutes($left),
        ]);
    }
    $lessons[0]->update(['type' => 'trial', 'enrollment_id' => null, 'funding_status' => 'free']);

    $sql = null;
    DB::listen(function (QueryExecuted $q) use (&$sql) {
        $sql ??= str_contains($q->sql, 'session_participants') ? $q : null;
    });
    $stars = app(StarsQuery::class)->for($this->student->id);
    $expected = array_sum(array_map(fn (array $p) => LessonStars::for('completed', CarbonImmutable::parse($p[1], 'UTC'), CarbonImmutable::parse($p[1], 'UTC')->addMinutes(30),
        CarbonImmutable::parse($p[1], 'UTC')->addMinutes($p[2]), $p[3] === null ? null : CarbonImmutable::parse($p[1], 'UTC')->addMinutes($p[3]))['stars'], $past));
    expect($stars['lessons'])->toBe($expected)->and($expected)->toBe(4 + 4 + 1)
        ->and(array_keys($stars['by_lesson']))->toBe([$lessons[0]->id, $lessons[1]->id, $lessons[2]->id]);

    // However long the academy's history (here three years of another student's lessons, every four hours), the read
    // starts from this student's own participations through their index, never from the academy's lessons by date.
    $other = User::factory()->withRole(Role::Student)->create();
    DB::statement(<<<'SQL'
        insert into sessions (id, academy_id, type, teacher_id, subject_id, starts_at, ends_at, status, funding_status, anchor_tz, created_at, updated_at)
        select gen_random_uuid(), app_current_tenant(), 'regular', ?, ?, t, t + interval '30 minutes', 'completed', 'funded', 'Etc/UTC', now(), now()
        from generate_series(timestamptz '2023-01-01 02:00+00', timestamptz '2026-09-30 02:00+00', interval '4 hours') t
    SQL, [$this->other->id, $this->quran]);
    DB::statement(<<<'SQL'
        insert into session_participants (academy_id, session_id, user_id, role, block_period)
        select academy_id, id, ?, 'student', tstzrange(starts_at, ends_at) from sessions where anchor_tz = 'Etc/UTC'
    SQL, [$other->id]);
    DB::connection('pgsql_owner')->statement('analyze sessions, session_participants');

    $plan = collect(DB::select('explain '.$sql->sql, $sql->bindings))->pluck('QUERY PLAN')->implode("\n");
    expect(DB::table('sessions')->count())->toBeGreaterThan(8000)
        ->and($plan)->toContain('session_participants_academy_id_user_id_index')
        ->and($plan)->not->toContain('Seq Scan on sessions');
    expect(app(StarsQuery::class)->for($this->student->id)['lessons'])->toBe($expected);
});
