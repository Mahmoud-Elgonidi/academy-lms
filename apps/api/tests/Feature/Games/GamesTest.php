<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\NameCreature;
use Modules\Games\Actions\SaveGameSettings;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Filament\Family\FamilyCreatures;
use Modules\Games\Filament\Family\FamilyGames;
use Modules\Games\Filament\Family\JourneyGuardian;
use Modules\Games\Filament\Family\JourneyPlay;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Games\Privacy\GamesData;
use Modules\Games\Queries\GamesWorld;
use Modules\Games\Queries\GuardianGlance;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\ChoosePracticeGame;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Filament\Teacher\HomeworkGame;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Games\StudioStats;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Http\ProblemException;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, QuranProgram::class, StarterGames::class);

/*
 * The games in an academy (docs/architecture/02, section 18): a learner
 * plays their own curriculum. Quests come from what to practise now
 * (homework, the last lesson, reviews, where the course stands), a round is
 * handed the learner's own items, what the game reports is scored on first
 * tries, the round counts as practice on the lessons and hands in a
 * practice homework, stars and mastered lessons hatch creatures, and the
 * academy and a guardian say how much play there is.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->installStarterGames();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->stranger = User::factory()->withRole(Role::Guardian)->create();
    lookAs($this->student, LearnerStage::Kids);

    $save = app(SaveNodeItems::class);
    $save->handle(Node::query()->findOrFail($this->lessons[0]), [
        ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: مالك يوم …', 'options' => ['الدين', 'العالمين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: اهدنا الصراط …', 'options' => ['المستقيم', 'الضالين'], 'answer' => 0],
        ['kind' => 'order', 'prompt' => 'رتّب', 'parts' => ['بسم الله', 'الحمد لله', 'الرحمن الرحيم']],
        ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ['kind' => 'pair', 'left' => 'اهدنا', 'right' => 'الصراط المستقيم'],
        ['kind' => 'pair', 'left' => 'إياك نعبد', 'right' => 'وإياك نستعين'],
    ], $this->admin->id);
    $save->handle(Node::query()->findOrFail($this->lessons[1]), [
        ['kind' => 'choice', 'prompt' => 'قل أعوذ برب …', 'options' => ['الناس', 'الفلق'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'من شر الوسواس …', 'options' => ['الخناس', 'الناس'], 'answer' => 0],
    ], $this->admin->id);
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function lookAs(User $student, LearnerStage $stage): void
{
    app(PeopleApi::class)->saveStudentProfile($student->id, null, $stage);
    app()->forgetInstance(FamilyView::class);
}

function gamesToken(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

/** Every item right first time, except the first $wrong (wrong, then right). */
function playedRound(array $items, int $wrong = 0): array
{
    $answers = [];
    foreach (array_values($items) as $i => $item) {
        if ($i < $wrong) {
            $answers[] = ['item' => $item['id'], 'correct' => false];
        }
        $answers[] = ['item' => $item['id'], 'correct' => true];
    }

    return $answers;
}

function practiceHomework(array $lessons, string $accepts = 'practice'): Homework
{
    return app(AssignHomework::class)->handle([
        'teacher_id' => test()->teacher->id, 'student_id' => test()->student->id, 'enrollment_id' => test()->booked->enrollmentId,
        'title' => 'جولة على سورة الناس', 'accepts' => $accepts, 'due_at' => CarbonImmutable::now()->addDays(2), 'practice_nodes' => $lessons,
    ]);
}

it('opens the learner\'s world: islands, creatures to find, the games their items fit, and a quest where the course stands', function () {

    $data = $this->withToken(gamesToken($this->student))->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/games/world')->assertOk()->json('data');

    expect($data['stage'])->toBe('kids')
        ->and($data['allowance'])->toMatchArray(['may' => true, 'limit' => 30, 'used' => 0, 'left' => 30])
        ->and($data['stars'])->toBe(0)
        ->and($data['egg'])->toBe(['have' => 0, 'need' => Round::STARS_PER_EGG])
        ->and(collect($data['islands'])->pluck('key')->all())->toBe(['birds', 'turtles', 'monkeys', 'penguins', 'volcano'])
        ->and(collect($data['islands'][0]['games'])->pluck('playable', 'key')->all())->toBe(['bird-gates' => true, 'bird-balloons' => true, 'bird-twins' => true])
        ->and($data['islands'][2]['games'])->toBe([])
        ->and(collect($data['islands'][0]['creatures'])->pluck('found')->unique()->all())->toBe([false])
        ->and($data['quests'])->toHaveCount(1)
        ->and($data['quests'][0])->toMatchArray(['reason' => 'current', 'title' => 'درس 1', 'items' => 7, 'done' => false])
        ->and($data['limits'])->toBeNull();
});

it('plays a round with the learner\'s own items, scores first tries, and keeps it as practice on the lesson', function () {
    $quest = 'current:'.$this->booked->enrollmentId;

    $start = $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'quest' => $quest])->assertCreated()->json('data');
    $items = $start['round']['items'];
    expect($start['game']['key'])->toBe('bird-balloons')
        ->and($start['page_url'])->toContain('/games/')
        ->and($start['focus']['reason'])->toBe('current')
        ->and(collect($items)->pluck('kind')->unique()->all())->toBe(['choice'])
        ->and($items)->toHaveCount(3)
        ->and($items[0])->toHaveKeys(['id', 'prompt', 'options', 'answer']);

    $answers = [...playedRound($items, wrong: 1), ['item' => 'not-in-this-round', 'correct' => true]];
    $done = $this->withToken(gamesToken($this->student))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => $answers])->assertOk()->json('data');
    expect($done)->toMatchArray(['stars' => 2, 'correct' => 2, 'total' => 3, 'creature' => null, 'homework' => null])
        ->and($done['egg'])->toBe(['have' => 2, 'need' => 12])
        ->and($done['seconds'])->toBeInt()->toBeGreaterThanOrEqual(0);

    $this->actAsTenant($this->academy);
    expect(DB::table('practice_rounds')->where('student_id', $this->student->id)->sole())->toMatchArray(['game' => 'bird-balloons', 'focus' => 'current', 'correct' => 2, 'total' => 3, 'stars' => 2])
        ->and((array) DB::table('practice_stats')->where('node_id', $this->lessons[0])->first(['rounds', 'answered', 'correct']))->toBe(['rounds' => 1, 'answered' => 3, 'correct' => 2]);

    // Finishing again answers the same round, counted once.
    $this->clearTenant();
    $this->withToken(gamesToken($this->student))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => playedRound($items)])->assertOk()->assertJsonPath('data.stars', 2);
    $this->actAsTenant($this->academy);
    expect(DB::table('practice_rounds')->count())->toBe(1);
});

it('hands in a practice homework by playing it, and keeps the best result on a homework that also asks for more', function () {
    $homework = practiceHomework([$this->lessons[1]]);
    $recorded = practiceHomework([$this->lessons[1]], accepts: 'audio');

    $world = $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->json('data');
    expect(collect($world['quests'])->pluck('reason')->all())->toBe(['homework', 'homework', 'current'])
        ->and(collect($world['quests'])->pluck('homework_id')->filter()->sort()->values()->all())->toBe(collect([$homework->id, $recorded->id])->sort()->values()->all());

    $start = $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'homework_id' => $homework->id])->assertCreated()->json('data');
    expect(collect($start['round']['items'])->pluck('prompt')->all())->toBe(['قل أعوذ برب …', 'من شر الوسواس …']);
    $this->withToken(gamesToken($this->student))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => playedRound($start['round']['items'])])
        ->assertOk()->assertJsonPath('data.homework', ['title' => 'جولة على سورة الناس', 'handed_in' => true]);

    $this->actAsTenant($this->academy);
    $homework->refresh();
    expect($homework->status)->toBe('submitted')
        ->and($homework->practice_result)->toMatchArray(['correct' => 2, 'total' => 2, 'stars' => 3])
        ->and($homework->submissions()->sole()->body)->toContain('2');

    // The recording homework keeps its best round and still waits for the recording.
    $this->clearTenant();
    $start = $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'homework_id' => $recorded->id])->json('data');
    $this->withToken(gamesToken($this->student))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => playedRound($start['round']['items'], wrong: 1)])
        ->assertOk()->assertJsonPath('data.homework.handed_in', false);
    $this->actAsTenant($this->academy);
    expect($recorded->refresh()->status)->toBe('assigned')
        ->and($recorded->practice_result['correct'])->toBe(1);
});

it('hatches a creature on the island played for every twelve stars, and one for each lesson mastered', function () {
    $this->actAsTenant($this->academy);
    $quest = 'current:'.$this->booked->enrollmentId;
    $hatched = [];
    for ($i = 0; $i < 4; $i++) {
        $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', $quest);
        $hatched[] = app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round']))['creature'];
    }
    expect(array_slice($hatched, 0, 3))->toBe([null, null, null])
        ->and($hatched[3]?->islandKey)->toBe('birds')
        ->and(CreatureFind::query()->where('student_id', $this->student->id)->sole()->source)->toBe('stars');

    // The teacher marks the first lesson mastered.
    app(AttendanceApi::class)->participantJoined($this->tuesday->id, $this->student->id, $this->tuesday->starts_at->addMinute());
    $this->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized($this->tuesday->academy_id, $this->tuesday->id, 'completed', []));
    app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $this->tuesday->id)->firstOrFail(), $this->teacher->id, [
        'engagement' => 5, 'overall' => 5, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'score' => 5]],
    ]);
    $mastery = CreatureFind::query()->where('student_id', $this->student->id)->where('source', 'mastery')->sole();
    expect($mastery->node_id)->toBe($this->lessons[0])
        ->and($mastery->creature_key)->not->toBe($hatched[3]->key);
});

it('turns the last lesson\'s report into a quest, and a teacher can ask for practice with the homework', function () {
    $this->actAsTenant($this->academy);
    app(AttendanceApi::class)->participantJoined($this->tuesday->id, $this->student->id, $this->tuesday->starts_at->addMinute());
    $this->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized($this->tuesday->academy_id, $this->tuesday->id, 'completed', []));
    CarbonImmutable::setTestNow($this->tuesday->starts_at->addHours(2));
    app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $this->tuesday->id)->firstOrFail(), $this->teacher->id, [
        'engagement' => 5, 'overall' => 5,
        'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced', 'score' => 4], ['node_id' => $this->lessons[1], 'outcome' => 'introduced', 'score' => 3]],
        'homework' => ['title' => 'راجع الفاتحة والناس', 'accepts' => 'text', 'due_days' => 2, 'practice' => true],
    ]);

    $homework = Homework::query()->where('student_id', $this->student->id)->sole();
    expect($homework->practice_nodes)->toBe([$this->lessons[0], $this->lessons[1]])
        ->and($homework->hasPractice())->toBeTrue();

    $quests = collect(app(GamesWorld::class)->for($this->student->id)['quests']);
    expect($quests->pluck('reason')->all())->toBe(['homework', 'last_lesson'])
        ->and($quests[1]['items'])->toBe(9);
});

it('keeps to the academy\'s choice and the guardian\'s: off, paused, and today\'s minutes', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 10:00', 'UTC'));

    // Only a guardian of this child sets their play; never the child, never another family.
    $this->withToken(gamesToken($this->student))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => false])->assertForbidden();
    $this->withToken(gamesToken($this->stranger))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => false])->assertNotFound();
    $this->withToken(gamesToken($this->guardian))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => true, 'daily_minutes' => 25])->assertUnprocessable();
    $this->withToken(gamesToken($this->guardian))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => false])
        ->assertOk()->assertJsonPath('data', ['enabled' => false, 'daily_minutes' => null, 'night_lock' => false, 'academy_minutes' => 30, 'choices' => [10, 15, 20, 30, 45, 60, 90, 120]]);
    $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons'])->assertForbidden()->assertJsonPath('type', fn ($t) => str_ends_with($t, 'games-paused'));

    // The guardian allows ten minutes, and ten minutes are played today.
    $this->withToken(gamesToken($this->guardian))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => true, 'daily_minutes' => 10])->assertOk();
    $this->actAsTenant($this->academy);
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(10));
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round']));
    $this->clearTenant();
    $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->assertJsonPath('data.allowance.may', false)->assertJsonPath('data.allowance.reason', 'limit');
    $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons'])->assertForbidden()->assertJsonPath('type', fn ($t) => str_ends_with($t, 'games-limit'));

    // The academy turns the games off.
    $this->actAsTenant($this->academy);
    app(SaveGameSettings::class)->handle($this->admin->id, false, 30);
    $this->clearTenant();
    $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons'])->assertForbidden()->assertJsonPath('type', fn ($t) => str_ends_with($t, 'games-off'));
});

it('plays only games made for the learner\'s look, with enough of their items', function () {
    $this->actAsTenant($this->academy);
    lookAs($this->student, LearnerStage::Teen);
    expect(fn () => app(StartPlay::class)->handle($this->student->id, $this->student->id, 'turtle-bridge'))
        ->toThrow(fn (ProblemException $e) => expect($e->getStatusCode())->toBe(404));

    lookAs($this->student, LearnerStage::Kids);
    app(SaveNodeItems::class)->handle(Node::query()->findOrFail($this->lessons[0]), [['kind' => 'choice', 'prompt' => 'س', 'options' => ['أ', 'ب'], 'answer' => 0]], $this->admin->id);
    expect(fn () => app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', 'current:'.$this->booked->enrollmentId))
        ->toThrow(fn (ProblemException $e) => expect($e->type)->toBe('games-not-enough-items'));
});

it('lets a guardian play on their child\'s screen, but no one finishes another family\'s round', function () {
    $start = $this->withToken(gamesToken($this->guardian))->postJson('/api/v1/games/plays', ['learner_id' => $this->student->id, 'game' => 'bird-twins'])->assertCreated()->json('data');
    expect(collect($start['round']['items'])->pluck('kind')->unique()->all())->toBe(['pair']);

    $this->withToken(gamesToken($this->stranger))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => []])->assertNotFound();
    $this->withToken(gamesToken($this->guardian))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => playedRound($start['round']['items'])])->assertOk();

    $this->actAsTenant($this->academy);
    expect(GamePlay::query()->sole()->played_by)->toBe($this->guardian->id)
        ->and(DB::table('practice_rounds')->value('played_by'))->toBe($this->guardian->id);
});

it('plays on the journey in the family area: a station\'s round in its frame, the result, a homework link, and the guardian\'s limits', function () {
    Filament::setCurrentPanel('family');
    $this->actingAs($this->student);
    $this->actAsTenant($this->academy);

    $page = Livewire::test(JourneyPlay::class)->assertOk()->dispatch('journey-play', game: 'bird-balloons', station: $this->lessons[0]);
    $round = $page->get('round');
    expect($round['src'])->toContain('/games/')
        ->and($round['stage'])->toBe('kids')
        ->and($round['station'])->toBe($this->lessons[0])
        ->and($round['items'])->not->toBeEmpty();
    $page->assertSeeHtml('data-round-player');

    $page->call('finish', playedRound($round['items']))->assertSet('round', null)->assertDispatched('journey-changed');
    expect($page->get('result'))->toMatchArray(['stars' => 3, 'correct' => count($round['items'])]);

    // A homework link (the old games page) leads to the journey, which starts that homework's round.
    $homework = practiceHomework([$this->lessons[1]]);
    Livewire::withQueryParams(['homework' => $homework->id])->test(FamilyGames::class)
        ->assertRedirect(route('filament.family.pages.journey', ['homework' => $homework->id]));
    $linked = Livewire::test(JourneyPlay::class, ['start' => ['game' => 'bird-balloons', 'homework' => $homework->id]]);
    expect($linked->get('round')['focus'])->toBe('جولة على سورة الناس');

    $this->actingAs($this->guardian);
    app()->forgetInstance(FamilyView::class);
    $guardian = Livewire::test(JourneyGuardian::class)->assertOk();
    expect($guardian->instance()->glance)->not->toBeNull()
        ->and($guardian->instance()->limits)->toMatchArray(['enabled' => true, 'daily_minutes' => null, 'night_lock' => false]);
    $guardian->call('setMinutes', 20)->call('setNight', true);
    expect((array) DB::table('game_limits')->where('student_id', $this->student->id)->first(['enabled', 'daily_minutes', 'night_lock']))
        ->toBe(['enabled' => true, 'daily_minutes' => 20, 'night_lock' => true]);
    $guardian->call('setEnabled', false)->call('setMinutes', null);
    expect((array) DB::table('game_limits')->where('student_id', $this->student->id)->first(['enabled', 'daily_minutes', 'night_lock']))
        ->toBe(['enabled' => false, 'daily_minutes' => null, 'night_lock' => true]);

    // The child cannot set their own limits.
    $this->actingAs($this->student);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(JourneyGuardian::class)->call('setMinutes', 120)->assertForbidden();
});

it('gives a copy of the learner\'s rounds, creatures and limits, and erases them', function () {
    $this->actAsTenant($this->academy);
    [$creature] = perfectRounds(4);
    app(NameCreature::class)->handle($this->student->id, $creature->key, 'ريشو');

    $copy = app(GamesData::class)->export($this->student->id);
    expect($copy->data['rounds'])->toHaveCount(4)
        ->and($copy->data['rounds'][0])->toMatchArray(['game' => 'bird-balloons', 'stars' => 3])
        ->and($copy->data['creatures'][0])->toMatchArray(['creature' => $creature->key, 'source' => 'stars', 'nickname' => 'ريشو']);

    expect(app(GamesData::class)->erase($this->student->id))->toMatchArray(['game_rounds' => 4])
        ->and(GamePlay::query()->count())->toBe(0);
});

/** Rounds of the current quest, every one right first time: three stars each. */
function perfectRounds(int $n): array
{
    $hatched = [];
    for ($i = 0; $i < $n; $i++) {
        $r = app(StartPlay::class)->handle(test()->student->id, test()->student->id, 'bird-balloons', 'current:'.test()->booked->enrollmentId);
        $hatched[] = app(FinishPlay::class)->handle($r['play'], test()->student->id, playedRound($r['round']))['creature'];
    }

    return array_values(array_filter($hatched));
}

it('lets the learner name what they hatched and choose a buddy, while a guardian only looks', function () {
    $this->actAsTenant($this->academy);
    [$first, $second] = perfectRounds(8);
    $this->clearTenant();

    $album = $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/creatures')->assertOk()->json('data');
    expect($album)->toHaveCount(20)
        ->and(collect($album)->where('found', true)->pluck('key')->all())->toBe([$first->key, $second->key])
        ->and(collect($album)->firstWhere('found', false))->toHaveKeys(['look', 'island', 'island_name'])
        ->and(collect($album)->firstWhere('key', $first->key))->toMatchArray(['source' => 'stars', 'nickname' => null, 'buddy' => false])
        ->and(collect($album)->firstWhere('key', $first->key)['names'])->toHaveCount(3)
        ->and(collect($album)->firstWhere('key', $first->key)['names'][0])->toBe(collect($album)->firstWhere('key', $first->key)['name'])
        ->and(collect($album)->firstWhere('found', false)['names'])->toBe([]);

    $url = '/api/v1/games/creatures/'.$first->key;
    $this->withToken(gamesToken($this->student))->putJson($url, ['name' => '  بندق   الصغير '])->assertOk()->assertJsonPath('data.nickname', 'بندق الصغير');
    $this->withToken(gamesToken($this->student))->putJson($url, ['name' => 'call 0100'])->assertUnprocessable();
    $this->withToken(gamesToken($this->student))->putJson($url, ['name' => 'www.example.com'])->assertUnprocessable();
    $this->withToken(gamesToken($this->student))->putJson($url, ['name' => str_repeat('ب', NameCreature::MAX + 1)])->assertUnprocessable();

    // One buddy at a time, only one the learner hatched.
    $this->withToken(gamesToken($this->student))->putJson($url, ['buddy' => true])->assertOk()->assertJsonPath('data.buddy', true);
    $this->withToken(gamesToken($this->student))->putJson('/api/v1/games/creatures/'.$second->key, ['buddy' => true])->assertOk();
    $world = $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->json('data');
    expect($world['buddy']['key'])->toBe($second->key)
        ->and($world['creatures_found'])->toBe(2)
        ->and($world['creatures_total'])->toBe(20);
    $unfound = collect($album)->firstWhere('found', false)['key'];
    $this->withToken(gamesToken($this->student))->putJson('/api/v1/games/creatures/'.$unfound, ['buddy' => true])->assertNotFound();

    // A guardian sees the album but leaves the names and the buddy to the child; another family sees nothing.
    $this->withToken(gamesToken($this->guardian))->getJson('/api/v1/games/creatures?learner_id='.$this->student->id)->assertOk();
    $this->withToken(gamesToken($this->guardian))->putJson($url, ['learner_id' => $this->student->id, 'name' => 'بسبوسة'])->assertForbidden();
    $this->withToken(gamesToken($this->stranger))->getJson('/api/v1/games/creatures?learner_id='.$this->student->id)->assertNotFound();

    // An empty name gives the creature its own back.
    $this->withToken(gamesToken($this->student))->putJson($url, ['name' => ''])->assertOk()->assertJsonPath('data.nickname', null);
    $this->actAsTenant($this->academy);
    expect(CreatureFind::query()->where('buddy', true)->pluck('creature_key')->all())->toBe([$second->key]);
});

it('locks the games at night on the child\'s own clock when a guardian asks', function () {
    $this->actAsTenant($this->academy);
    DB::table('users')->where('id', $this->student->id)->update(['timezone' => 'Asia/Riyadh']);
    $this->clearTenant();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 19:30', 'UTC')); // 22:30 in Riyadh

    $this->withToken(gamesToken($this->guardian))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => true, 'night_lock' => true])
        ->assertOk()->assertJsonPath('data.night_lock', true)->assertJsonPath('data.enabled', true);
    $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')
        ->assertJsonPath('data.allowance.may', false)->assertJsonPath('data.allowance.reason', 'night')->assertJsonPath('data.allowance.night_lock', true);
    $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons'])
        ->assertForbidden()->assertJsonPath('type', fn ($t) => str_ends_with($t, 'games-night'));

    // Morning in Riyadh: play again. Without the lock, the night is the child's.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 04:30', 'UTC'));
    $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->assertJsonPath('data.allowance.may', true);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 20:00', 'UTC'));
    $this->withToken(gamesToken($this->guardian))->putJson('/api/v1/games/limits', ['learner_id' => $this->student->id, 'enabled' => true, 'night_lock' => false])->assertOk();
    $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons'])->assertCreated();
});

it('races a quest against its own best time and counts the days in a row', function () {
    $this->actAsTenant($this->academy);
    $quest = 'current:'.$this->booked->enrollmentId;
    foreach ([['2026-10-05 10:00', 50], ['2026-10-06 10:00', 40], ['2026-10-07 10:00', 45]] as [$at, $seconds]) {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($at, 'UTC'));
        $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', $quest);
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds($seconds));
        app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round'], wrong: $seconds === 40 ? 1 : 0));
    }
    // A round on another lesson does not count for this quest's race.
    $homework = practiceHomework([$this->lessons[1]]);
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', null, $homework->id);
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round']));

    $world = app(GamesWorld::class)->for($this->student->id);
    expect(collect($world['quests'])->firstWhere('reason', 'current'))->toMatchArray(['reason' => 'current', 'rounds' => 3, 'best_seconds' => 40, 'last_seconds' => 45])
        ->and($world['streak'])->toBe(3)
        ->and(collect($world['history'])->pluck('stars')->all())->toBe([3, 2, 3, 3])
        ->and($world['history'][1]['share'])->toBe(0.67);

    $this->clearTenant();
    $api = $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->assertOk()->json('data');
    expect($api['streak'])->toBe(3)
        ->and(collect($api['quests'])->firstWhere('reason', 'current'))->toMatchArray(['rounds' => 3, 'best_seconds' => 40, 'last_seconds' => 45]);
    $this->actAsTenant($this->academy);

    // A day without play keeps the streak until the day ends, and then it starts again.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 18:00', 'UTC'));
    expect(app(GamesWorld::class)->for($this->student->id)['streak'])->toBe(3);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 18:00', 'UTC'));
    expect(app(GamesWorld::class)->for($this->student->id)['streak'])->toBe(0);
});

it('shows a guardian the week: the lesson practised most, the hardest question, the best round and today\'s creature', function () {
    $this->actAsTenant($this->academy);
    $quest = 'current:'.$this->booked->enrollmentId;
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', $quest);
    $hard = $r['round'][0];
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round'], wrong: 1));
    [$creature] = perfectRounds(4);

    $glance = app(GuardianGlance::class)->for($this->student->id);
    expect($glance['rounds'])->toBe(5)
        ->and($glance['practised'])->toBe(['title' => 'Lesson 1', 'rounds' => 5, 'games' => 1])
        ->and($glance['hardest'])->toBe(['text' => $hard['prompt'].' → '.$hard['options'][$hard['answer']], 'wrong' => 1])
        ->and($glance['best'])->toMatchArray(['correct' => 3, 'total' => 3])
        ->and($glance['hatched'])->toMatchArray(['key' => $creature->key, 'source' => 'stars', 'found' => 1]);

    // The guardian cheers the child for it from the family area; the child cannot cheer themself.
    Filament::setCurrentPanel('family');
    $this->actingAs($this->guardian);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(JourneyGuardian::class)->assertSee($glance['hatched']['name'])->call('cheer')->assertOk();
    expect(Cheer::query()->where('student_id', $this->student->id)->sole())->toMatchArray(['from_id' => $this->guardian->id, 'kind' => 'heart']);
    $this->actingAs($this->student);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(JourneyGuardian::class)->call('cheer')->assertForbidden();

    // A week later the glance is quiet again.
    expect(app(GuardianGlance::class)->for($this->student->id, CarbonImmutable::now()->addDays(8)))
        ->toMatchArray(['rounds' => 0, 'practised' => null, 'hardest' => null, 'best' => null, 'hatched' => null]);
});

it('hatches in the family area, names the creature and opens the album, where the child chooses a buddy', function () {
    Filament::setCurrentPanel('family');
    $this->actingAs($this->student);
    $this->actAsTenant($this->academy);
    perfectRounds(3);

    $page = Livewire::test(JourneyPlay::class)->dispatch('journey-play', game: 'bird-balloons', station: $this->lessons[0]);
    $page->call('finish', playedRound($page->get('round')['items']));
    $creature = $page->get('result')['creature'];
    expect($creature)->toMatchArray(['found' => 1, 'total' => 20])
        ->and($creature['names'])->toHaveCount(3)
        ->and($creature['names'][0])->toBe($creature['name']);
    $page->assertSeeHtml('LmsIslands.hatch');

    $page->call('nameCreature', $creature['key'], $creature['names'][1])
        ->assertRedirect(FamilyCreatures::getUrl(['new' => $creature['key']]));
    expect(CreatureFind::query()->sole()->nickname)->toBe($creature['names'][1]);

    $album = Livewire::withQueryParams(['new' => $creature['key']])->test(FamilyCreatures::class)->assertOk()->assertSee($creature['names'][1]);
    expect($album->instance()->creatures)->toHaveCount(20);
    $album->call('chooseBuddy', $creature['key'])->call('rename', $creature['key'], 'ريشو');
    expect(CreatureFind::query()->sole())->toMatchArray(['buddy' => true, 'nickname' => 'ريشو']);

    // The guardian sees it, and leaves its name to the child.
    $this->actingAs($this->guardian);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(FamilyCreatures::class)->assertOk()->assertSee('ريشو')->call('chooseBuddy', $creature['key'])->assertForbidden();
    Livewire::test(JourneyPlay::class)->call('nameCreature', $creature['key'], 'بسبوسة')->assertForbidden();
});

/** The first lesson taught, marked done and reported by its teacher. */
function reportedLesson(): SessionReport
{
    app(AttendanceApi::class)->participantJoined(test()->tuesday->id, test()->student->id, test()->tuesday->starts_at->addMinute());
    test()->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized(test()->tuesday->academy_id, test()->tuesday->id, 'completed', []));
    CarbonImmutable::setTestNow(test()->tuesday->starts_at->addHours(2));
    $report = SessionReport::query()->where('session_id', test()->tuesday->id)->firstOrFail();
    app(SubmitSessionReport::class)->handle($report, test()->teacher->id, [
        'engagement' => 5, 'overall' => 5,
        'items' => [['node_id' => test()->lessons[0], 'outcome' => 'practiced', 'score' => 4], ['node_id' => test()->lessons[1], 'outcome' => 'introduced', 'score' => 3]],
    ]);

    return $report->refresh();
}

it('makes a lesson\'s homework the game its teacher chose, due before the next lesson, and offers that game first', function () {
    $this->actAsTenant($this->academy);
    $report = reportedLesson();
    $next = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->where('starts_at', '>', CarbonImmutable::now())->orderBy('starts_at')->firstOrFail();

    $homework = app(ChoosePracticeGame::class)->handle($report, $this->teacher->id, 'bird-twins', practiceOnly: true, beforeNext: true);
    expect($homework)->toMatchArray(['practice_game' => 'bird-twins', 'accepts' => 'practice', 'report_id' => $report->id, 'practice_nodes' => [$this->lessons[0], $this->lessons[1]]])
        ->and($homework->due_at->equalTo($next->starts_at))->toBeTrue()
        // The learner reads it: written in the academy's language, whatever the teacher's screen is in.
        ->and($homework->title)->toBe(__('learning::homework.game.title', ['game' => app(GameCatalog::class)->game('bird-twins')->title('ar')], 'ar'));

    $quests = app(GamesWorld::class)->for($this->student->id)['quests'];
    expect($quests[0])->toMatchArray(['reason' => 'homework', 'homework_id' => $homework->id])
        ->and($quests[0]['game']['key'])->toBe('bird-twins');

    // Changed while nothing was handed in: another game, and the homework asks for more than playing.
    $again = app(ChoosePracticeGame::class)->handle($report, $this->teacher->id, 'bird-balloons', practiceOnly: false, beforeNext: false);
    expect($again->id)->toBe($homework->id)
        ->and($again->refresh())->toMatchArray(['practice_game' => 'bird-balloons', 'accepts' => 'any'])
        ->and(Homework::query()->count())->toBe(1);

    expect(fn () => app(ChoosePracticeGame::class)->handle($report, $this->teacher->id, 'no-such-game', true, true))->toThrow(ValidationException::class)
        ->and(fn () => app(ChoosePracticeGame::class)->handle($report, $this->admin->id, 'bird-twins', true, true))->toThrow(AuthorizationException::class);

    // Once the child played it in, it stays as it was.
    app(ChoosePracticeGame::class)->handle($report, $this->teacher->id, 'bird-balloons', practiceOnly: true, beforeNext: true);
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', 'homework:'.$homework->id);
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round']));
    expect($homework->refresh()->status)->toBe('submitted')
        ->and(fn () => app(ChoosePracticeGame::class)->handle($report, $this->teacher->id, 'bird-twins', true, true))->toThrow(ValidationException::class);
});

it('takes the teacher from the report to the homework game: what was taught, the games that fit, a try, and send', function () {
    Filament::setCurrentPanel('teach');
    $this->actAsTenant($this->academy);
    app(AttendanceApi::class)->participantJoined($this->tuesday->id, $this->student->id, $this->tuesday->starts_at->addMinute());
    $this->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized($this->tuesday->academy_id, $this->tuesday->id, 'completed', []));
    $report = SessionReport::query()->where('session_id', $this->tuesday->id)->firstOrFail();
    $this->actingAs($this->teacher);

    $form = Livewire::test(WriteReport::class, ['report' => $report->id]);
    $key = array_key_first($form->get('data.items'));
    $form->set("data.items.{$key}.outcome", 'practiced')->set("data.items.{$key}.score", 4)
        ->set('data.engagement', 5)->set('data.overall', 4)
        ->call('submit')->assertHasNoErrors()
        ->assertRedirect(HomeworkGame::getUrl(['report' => $report->id]));

    $page = Livewire::test(HomeworkGame::class, ['report' => $report->id])->assertOk()->assertSee('Nest Twins');
    $games = collect($page->instance()->games)->keyBy('key');
    expect($games->keys()->all())->toContain('bird-balloons', 'bird-twins', 'bird-gates')
        ->and($games['bird-twins'])->toMatchArray(['fits' => true, 'items' => 3])
        ->and($games['bird-balloons']['fits'])->toBeTrue()
        ->and($page->get('game'))->not->toBeNull();

    $page->call('pick', 'bird-twins')->call('tryIt');
    expect(collect($page->get('trying')['items'])->pluck('kind')->unique()->all())->toBe(['pair'])
        ->and(GamePlay::query()->count())->toBe(0);
    $page->call('stopTrying')->assertSet('trying', null)
        ->call('send')->assertDispatched('homework-game-sent');
    expect(Homework::query()->where('report_id', $report->id)->sole())->toMatchArray(['practice_game' => 'bird-twins', 'accepts' => 'practice']);

    // Another teacher cannot open it.
    $this->actingAs(User::factory()->withRole(Role::Teacher)->create());
    Livewire::test(HomeworkGame::class, ['report' => $report->id])->assertForbidden();
});

it('gives the studio counts across academies: players, rounds, eggs, and how each question in a round goes first time', function () {
    $this->actAsTenant($this->academy);
    $quest = 'current:'.$this->booked->enrollmentId;
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', $quest);
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round'], wrong: 1));
    perfectRounds(4);
    app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', $quest); // left open
    $balloons = app(GameCatalog::class)->game('bird-balloons')->id;
    $this->clearTenant();

    $stats = app(StudioStats::class);
    expect($stats->overview())->toBe(['players' => 1, 'academies' => 1, 'rounds' => 5, 'per_player' => 5.0, 'finished' => 83, 'eggs' => 1, 'mastery_eggs' => 0])
        ->and($stats->roundsByGame())->toBe([$balloons => 5])
        ->and($stats->game($balloons))->toMatchArray(['rounds' => 5, 'finished' => 83, 'stars' => 2.8, 'positions' => [80, 100, 100], 'academies' => 1]);
});

it('gives the apps what the screens show: the hatch names, and a guardian\'s look at the week', function () {
    $quest = 'current:'.$this->booked->enrollmentId;
    $hatched = null;
    for ($i = 0; $i < 4; $i++) {
        $start = $this->withToken(gamesToken($this->student))->postJson('/api/v1/games/plays', ['game' => 'bird-balloons', 'quest' => $quest])->assertCreated()->json('data');
        $hatched = $this->withToken(gamesToken($this->student))->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/games/plays/'.$start['play_id'].'/finish', ['answers' => playedRound($start['round']['items'])])->assertOk()->json('data.creature');
    }
    expect($hatched)->toMatchArray(['found' => 1, 'total' => 20])
        ->and($hatched['names'])->toHaveCount(3)
        ->and($hatched['names'][0])->toBe($hatched['name'])
        ->and($hatched)->toHaveKeys(['key', 'look', 'island', 'story']);

    $this->withToken(gamesToken($this->student))->getJson('/api/v1/games/world')->assertJsonPath('data.glance', null);
    $glance = $this->withToken(gamesToken($this->guardian))->getJson('/api/v1/games/world?learner_id='.$this->student->id)->assertOk()->json('data.glance');
    expect($glance['rounds'])->toBe(4)
        ->and($glance['practised'])->toBe(['title' => 'Lesson 1', 'rounds' => 4, 'games' => 1])
        ->and($glance['hardest'])->toBeNull()
        ->and($glance['best'])->toMatchArray(['correct' => 3, 'total' => 3])
        ->and($glance['hatched'])->toMatchArray(['key' => $hatched['key'], 'source' => 'stars', 'found' => 1]);
});
