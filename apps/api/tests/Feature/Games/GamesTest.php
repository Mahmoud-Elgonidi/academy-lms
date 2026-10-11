<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\SaveGameSettings;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Filament\Family\FamilyGames;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Games\Privacy\GamesData;
use Modules\Games\Queries\GamesWorld;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
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
        ->and($done['egg'])->toBe(['have' => 2, 'need' => 12]);

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
        ->assertOk()->assertJsonPath('data', ['enabled' => false, 'daily_minutes' => null, 'academy_minutes' => 30, 'choices' => [10, 15, 20, 30, 45, 60, 90, 120]]);
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

it('plays in the family area: a quest, the round in its frame, the result, and the guardian\'s limits', function () {
    Filament::setCurrentPanel('family');
    $this->actingAs($this->student);
    $this->actAsTenant($this->academy);

    $page = Livewire::test(FamilyGames::class)->assertOk();
    $quest = $page->instance()->world['quests'][0];
    $page->call('play', $quest['game']['id'], $quest['key']);
    $round = $page->get('round');
    expect($round['src'])->toContain('/games/')
        ->and($round['stage'])->toBe('kids')
        ->and($round['items'])->not->toBeEmpty();
    $page->assertSeeHtml('data-round-player');

    $page->call('finish', playedRound($round['items']))->assertSet('round', null);
    expect($page->get('result'))->toMatchArray(['stars' => 3, 'correct' => count($round['items'])])
        ->and($page->instance()->limits)->toBeNull();

    // A homework link starts that homework's round.
    $homework = practiceHomework([$this->lessons[1]]);
    $linked = Livewire::withQueryParams(['homework' => $homework->id])->test(FamilyGames::class);
    expect($linked->get('round')['focus'])->toBe('جولة على سورة الناس');

    $this->actingAs($this->guardian);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(FamilyGames::class)->call('saveLimits', true, 20);
    expect(DB::table('game_limits')->where('student_id', $this->student->id)->value('daily_minutes'))->toBe(20);
});

it('gives a copy of the learner\'s rounds, creatures and limits, and erases them', function () {
    $this->actAsTenant($this->academy);
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons');
    app(FinishPlay::class)->handle($r['play'], $this->student->id, playedRound($r['round']));

    $copy = app(GamesData::class)->export($this->student->id);
    expect($copy->data['rounds'])->toHaveCount(1)
        ->and($copy->data['rounds'][0])->toMatchArray(['game' => 'bird-balloons', 'stars' => 3]);

    expect(app(GamesData::class)->erase($this->student->id))->toMatchArray(['game_rounds' => 1])
        ->and(GamePlay::query()->count())->toBe(0);
});
