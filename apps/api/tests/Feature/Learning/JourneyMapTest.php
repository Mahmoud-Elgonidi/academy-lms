<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Games\Actions\SaveGameSettings;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Domain\Journey\LessonStars;
use Modules\Learning\Domain\Journey\Stars;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, QuranProgram::class, StarterGames::class);

/*
 * The journey (docs/architecture/02, section 18): the academy's own
 * curriculum as islands (levels) and stations (lessons), the missions to do
 * now (the next lesson, the teacher's homework, reviews), one reckoning of
 * stars (a lesson by how late the student came in and whether they stayed to
 * the end; practice, homework, mastered lessons, the week's chest), the games
 * on the lessons the student may practise, and nothing tied to a subject: an
 * academy teaching English gets the same journey over its own program. The
 * fixture's week is Saturday 3 to Friday 9 October on the student's clock
 * (London), with a lesson on Tuesday 6 October, 18:00 Cairo.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->installStarterGames();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    app(PeopleApi::class)->saveStudentProfile($this->student->id, null, LearnerStage::Kids);
    app()->forgetInstance(FamilyView::class);
    app(SaveNodeItems::class)->handle(Node::query()->findOrFail($this->lessons[0]), [
        ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: مالك يوم …', 'options' => ['الدين', 'العالمين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: اهدنا الصراط …', 'options' => ['المستقيم', 'الضالين'], 'answer' => 0],
        ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ['kind' => 'pair', 'left' => 'اهدنا', 'right' => 'الصراط المستقيم'],
        ['kind' => 'pair', 'left' => 'إياك نعبد', 'right' => 'وإياك نستعين'],
    ], $this->admin->id);
    app(SaveNodeItems::class)->handle(Node::query()->findOrFail($this->lessons[5]), [
        ['kind' => 'choice', 'prompt' => 'المد الطبيعي كام حركة؟', 'options' => ['حركتين', 'أربع'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'المد المتصل كام حركة؟', 'options' => ['أربع أو خمس', 'حركتين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'المد اللازم كام حركة؟', 'options' => ['ست', 'حركتين'], 'answer' => 0],
    ], $this->admin->id);
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function mapApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

/** A lesson happens: the student comes in $lateMin after the start and, when $leftMin is given, leaves that long after the start. */
function mapLesson(Session $lesson, ?int $lateMin = 1, ?int $leftMin = null, string $outcome = 'completed'): void
{
    if ($lateMin !== null && $outcome === 'completed') {
        app(AttendanceApi::class)->participantJoined($lesson->id, test()->student->id, $lesson->starts_at->addMinutes($lateMin));
        if ($leftMin !== null) {
            app(AttendanceApi::class)->participantLeft($lesson->id, test()->student->id, $lesson->starts_at->addMinutes($leftMin));
        }
    }
    $lesson->update(['status' => $outcome]);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, $outcome, []));
}

it('counts a lesson\'s stars by how late the student came in and whether they stayed to the end', function () {
    $start = CarbonImmutable::parse('2026-10-06 15:00', 'UTC');
    $end = $start->addMinutes(30);
    $stars = fn (?int $late, ?int $left, string $status = 'completed') => LessonStars::for($status, $start, $end,
        $late === null ? null : $start->addMinutes($late), $left === null ? null : $start->addMinutes($left))['stars'] ?? null;

    expect($stars(0, null))->toBe(4)              // on time, never left: stayed to the end
        ->and($stars(3, 29))->toBe(4)             // three minutes is still on time; left in the last five minutes
        ->and($stars(3, 20))->toBe(3)             // left early
        ->and($stars(7, null))->toBe(3)           // a little late, stayed
        ->and($stars(10, 10))->toBe(2)
        ->and($stars(15, null))->toBe(2)          // late, stayed
        ->and($stars(15, 16))->toBe(1)
        ->and($stars(null, null))->toBe(1)        // taught, but nobody saw them come in
        ->and($stars(null, null, 'student_absent'))->toBe(0)
        ->and($stars(1, null, 'cancelled'))->toBeNull()
        ->and($stars(1, null, 'teacher_absent'))->toBeNull()
        ->and($stars(1, null, 'needs_review'))->toBeNull()
        ->and($stars(1, null, 'scheduled'))->toBeNull()
        ->and(Stars::rank(0)['key'])->toBe('beginner')
        ->and(Stars::rank(85))->toMatchArray(['key' => 'star', 'next' => 150, 'next_key' => 'hero'])
        ->and(Stars::homework(CarbonImmutable::parse('2026-10-08 18:00'), CarbonImmutable::parse('2026-10-08 17:00')))->toBe(2)
        ->and(Stars::homework(CarbonImmutable::parse('2026-10-08 18:00'), CarbonImmutable::parse('2026-10-09 09:00')))->toBe(1);
});

it('draws the curriculum\'s levels as islands and its lessons as stations, with the lesson coming up as a mission', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:55', 'UTC'));   // five minutes before Tuesday's lesson
    $token = mapApp($this->student);

    $data = $this->withToken($token)->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/learning/journey/map')
        ->assertOk()->assertJsonPath('data.stage', 'kids')->assertJsonPath('data.viewer', 'self')->assertJsonPath('data.style', 'playful')
        ->json('data');
    $course = $data['courses'][0];
    expect($course['has_curriculum'])->toBeTrue()
        ->and(array_column($course['islands'], 'title'))->toBe(['المستوى الأول', 'المستوى الثاني'])
        ->and(array_column($course['islands'], 'state'))->toBe(['here', 'ahead'])
        // The levels are drawn as the world's islands in turn.
        ->and($course['islands'][0]['art']['key'])->toBe('birds')
        ->and($course['islands'][1]['art']['key'])->toBe('turtles')
        ->and(array_column($course['islands'][0]['stations'], 'id'))->toBe(array_slice($this->lessons, 0, 5))
        ->and($course['islands'][0]['stations'][0])->toMatchArray(['state' => 'here', 'playable' => true, 'unit' => 'الحروف'])
        // A lesson still ahead is on the map but not practised yet.
        ->and($course['islands'][1]['stations'][1])->toMatchArray(['state' => 'todo', 'playable' => false, 'games' => []]);

    // The current lesson plays in the games that fit its items; the island's own games come first.
    $games = $course['islands'][0]['stations'][0]['games'];
    expect($games)->not->toBeEmpty()->and($games[0]['island'])->toBe('birds');

    $lesson = collect($data['missions'])->firstWhere('kind', 'lesson');
    expect($lesson)->toMatchArray(['lesson_id' => $this->tuesday->id, 'station_id' => $this->lessons[0], 'stars' => 4])
        ->and($lesson['join']['open'])->toBeTrue()
        ->and($course['islands'][0]['stations'][0]['missions'])->toContain('lesson:'.$this->tuesday->id)
        ->and($data['games']['egg'])->toMatchArray(['have' => 0, 'need' => Stars::PER_EGG, 'ready' => false]);
});

it('gives the stars of a lesson and of homework, opens the week\'s chest once, and fills the egg', function () {
    mapLesson($this->tuesday, lateMin: 1);
    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId,
        'title' => 'سمّع سورة الفلق', 'due_at' => CarbonImmutable::parse('2026-10-08 18:00', 'Africa/Cairo'),
    ]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $token = mapApp($this->student);
    $data = $this->withToken($token)->getJson('/api/v1/learning/journey/map')->assertOk()->json('data');
    expect($data['stars']['from']['lessons'])->toBe(4)
        ->and($data['last_lesson'])->toMatchArray(['id' => $this->tuesday->id, 'stars' => 4, 'on_time' => true, 'stayed' => true])
        ->and(collect($data['missions'])->firstWhere('kind', 'homework'))->toMatchArray(['homework_id' => $homework->id, 'state' => 'open', 'stars' => 2])
        ->and($data['chest'])->toMatchArray(['period' => '2026-10-03', 'target' => 2, 'progress' => 1, 'state' => 'active', 'can_open' => false]);

    $this->withToken($token)->postJson('/api/v1/learning/journey/chest', ['period' => '2026-10-03'], ['Idempotency-Key' => 'early'])
        ->assertUnprocessable()->assertJsonPath('errors.period.0', __('learning::map.chest.not_ready'));

    $this->actAsTenant($this->academy);
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'سمّعتها', null);
    $this->clearTenant();
    $data = $this->withToken($token)->getJson('/api/v1/learning/journey/map')->json('data');
    expect($data['stars']['from']['homework'])->toBe(2)
        ->and($data['chest'])->toMatchArray(['state' => 'done', 'progress' => 2, 'can_open' => true])
        ->and($data['chest']['sticker']['key'])->toBe('lion');

    // A guardian watches; only the student opens it.
    $this->withToken(mapApp($this->guardian))->postJson('/api/v1/learning/journey/chest', ['period' => '2026-10-03'], ['Idempotency-Key' => 'g'])
        ->assertForbidden();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);
    $this->withToken($token)->postJson('/api/v1/learning/journey/chest', ['period' => '2026-10-03'], ['Idempotency-Key' => 'open'])
        ->assertCreated()->assertJsonPath('data.stars', Stars::CHEST)->assertJsonPath('data.total', 16)->assertJsonPath('data.sticker.key', 'lion');
    $this->withToken($token)->postJson('/api/v1/learning/journey/chest', ['period' => '2026-10-03'], ['Idempotency-Key' => 'again'])
        ->assertUnprocessable();
    $this->actAsTenant($this->academy);
    expect(ChallengeClaim::query()->where('student_id', $this->student->id)->where('challenge', 'chest')->count())->toBe(1);
    $this->clearTenant();

    // 16 stars: an egg is ready, and the child taps it open on the island they are on.
    $data = $this->withToken($token)->getJson('/api/v1/learning/journey/map')->json('data');
    expect($data['stars']['total'])->toBe(16)->and($data['games']['egg'])->toMatchArray(['ready' => true])
        ->and($data['stickers']['earned'])->toBe(1)
        ->and($data['stickers']['items'][0])->toMatchArray(['key' => 'lion', 'earned' => true])
        ->and($data['stickers']['items'][1]['earned'])->toBeFalse();
    $creature = $this->withToken($token)->postJson('/api/v1/games/hatch', ['island' => 'birds'], ['Idempotency-Key' => 'hatch'])
        ->assertCreated()->json('data.creature');
    expect($creature['island'])->toBe('birds')->and($creature['names'])->toHaveCount(3);
    $this->withToken($token)->postJson('/api/v1/games/hatch', [], ['Idempotency-Key' => 'hatch-2'])->assertCreated()->assertJsonPath('data.creature', null);
    $this->actAsTenant($this->academy);
    expect(CreatureFind::query()->where('student_id', $this->student->id)->where('source', 'stars')->count())->toBe(1);
});

it('plays a round on one station of the journey, and only on one the student may practise now', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $token = mapApp($this->student);
    $round = $this->withToken($token)->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'station' => $this->lessons[0]])
        ->assertCreated()->json('data');
    $this->actAsTenant($this->academy);
    expect(collect(GamePlay::query()->findOrFail($round['play_id'])->items)->pluck('node')->unique()->values()->all())->toBe([$this->lessons[0]])
        ->and($round['game']['island'])->toBe('birds')
        ->and(collect($round['round']['items'])->pluck('dir')->unique()->all())->toBe(['rtl']);

    // The second level's lesson is still ahead: no round on it yet.
    $this->withToken($token)->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'station' => $this->lessons[5]])->assertNotFound();
});

it('keeps the journey without games when the academy turns them off, and without stars in the plain style', function () {
    $this->actAsTenant($this->academy);
    app(SaveGameSettings::class)->handle($this->admin->id, false, null);
    $token = mapApp($this->student);
    $data = $this->withToken($token)->getJson('/api/v1/learning/journey/map')->assertOk()->json('data');
    expect($data['games'])->toBeNull()
        ->and($data['courses'][0]['islands'])->toHaveCount(2)
        ->and($data['courses'][0]['islands'][0]['stations'][0]['games'])->toBe([]);

    $this->actAsTenant($this->academy);
    app(SaveJourneyStyle::class)->handle('plain');
    $data = $this->withToken(mapApp($this->student))->getJson('/api/v1/learning/journey/map')->assertOk()->json('data');
    expect($data['style'])->toBe('plain')->and($data['stars']['total'])->toBe(0)->and($data['chest'])->toBeNull()->and($data['games'])->toBeNull()
        ->and($data['stickers'])->toBeNull();
});

it('lets a guardian look at their child\'s journey, never someone else\'s', function () {
    $token = mapApp($this->guardian);
    $this->withToken($token)->getJson('/api/v1/learning/journey/map?learner_id='.$this->student->id)
        ->assertOk()->assertJsonPath('data.viewer', 'guardian')->assertJsonPath('data.chest.can_open', false)
        ->assertJsonPath('data.games.limits.enabled', true);

    $this->actAsTenant($this->academy);
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $this->withToken(mapApp($stranger))->getJson('/api/v1/learning/journey/map?learner_id='.$this->student->id)->assertNotFound();
});

it('keeps each academy\'s curriculum its own: an English lesson\'s items read left to right in an Arabic app', function () {
    $other = $this->actAsTenant($this->createAcademy(['slug' => 'speak']));
    $english = app(CatalogApi::class)->createSubject('إنجليزي', 'English');
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => $english, 'name_ar' => 'إنجليزي للحياة اليومية', 'name_en' => 'Everyday English']);
    $version = $program->workingVersion();
    $save = app(SaveNode::class);
    $lessons = [];
    foreach (['Starter', 'Elementary', 'Pre-intermediate'] as $level) {
        $l = $save->handle($version, null, ['kind' => 'level', 'title_ar' => $level, 'title_en' => $level]);
        $lessons[] = $save->handle($version, null, ['kind' => 'lesson', 'parent_id' => $l->id, 'title_ar' => "Greetings ({$level})", 'title_en' => "Greetings ({$level})"])->id;
    }
    app(PublishVersion::class)->handle($version, null);
    app(SaveNodeItems::class)->handle(Node::query()->findOrFail($lessons[0]), [
        ['kind' => 'choice', 'prompt' => 'Choose the right greeting:', 'options' => ['Good morning!', 'Good night!'], 'answer' => 0],
    ], null);
    $items = app(CurriculumApi::class)->practiceItems([$lessons[0]])[$lessons[0]];
    expect($items[0]->forRound()['dir'])->toBe('ltr');

    // The Quran academy's student sees nothing of it, and its levels are three islands in turn.
    $this->clearTenant();
    $this->actAsTenant($this->academy);
    $mine = app(JourneyMap::class)->for($this->student->id, 'kids');
    expect(collect($mine['courses'])->pluck('program')->all())->toBe(['Nour Al-Bayan']);
    $this->actAsTenant($other);
    expect(Node::query()->whereIn('id', $this->lessons)->count())->toBe(0);
});
