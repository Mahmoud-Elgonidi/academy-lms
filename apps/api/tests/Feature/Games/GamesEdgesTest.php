<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\HatchCreature;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Actions\SaveGameSettings;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Games\Queries\PlayAllowance;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\ChoosePracticeGame;
use Modules\Learning\Actions\OpenWeeklyChest;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\PracticeRoundEntry;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Games\PlatformGameCatalog;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Http\ProblemException;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, QuranProgram::class, StarterGames::class);

/*
 * The games at their edges (docs/architecture/02, section 18): two requests
 * at the same moment (an egg, the week's chest) count once, held by the
 * database; a round takes only a station the learner may practise and
 * scores only its own items; the day's minutes and the night lock follow
 * the learner's clock across midnight and the clocks changing; the homework
 * a journey offers can be played however late, and the game a teacher picks
 * must be one the learner can play.
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
    ], $this->admin->id);
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function geToken(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

/** Stars from practice rounds (three each), as finished rounds would bring them. */
function geStars(int $rounds): void
{
    for ($i = 0; $i < $rounds; $i++) {
        PracticeRoundEntry::query()->create([
            'student_id' => test()->student->id, 'enrollment_id' => test()->booked->enrollmentId, 'game' => 'bird-balloons', 'focus' => 'free',
            'correct' => 3, 'total' => 3, 'stars' => 3, 'seconds' => 60, 'played_by' => test()->student->id, 'played_at' => CarbonImmutable::now(),
        ]);
    }
}

/** A second connection to the same database as the academy, as another request would hold. */
function geOtherRequest(string $academyId): Connection
{
    config(['database.connections.pgsql_other' => config('database.connections.pgsql')]);
    $other = DB::connection('pgsql_other');
    $other->select("select set_config('app.tenant_id', ?, false)", [$academyId]);

    return $other;
}

function geHomework(string $title, CarbonImmutable $due, string $accepts = 'practice', ?string $game = null): Homework
{
    return app(AssignHomework::class)->handle([
        'teacher_id' => test()->teacher->id, 'student_id' => test()->student->id, 'enrollment_id' => test()->booked->enrollmentId,
        'title' => $title, 'accepts' => $accepts, 'due_at' => $due, 'practice_nodes' => [test()->lessons[0]], 'practice_game' => $game,
    ]);
}

it('hatches one creature for one egg when the map\'s tap and a round\'s end come at the same moment', function () {
    $this->actAsTenant($this->academy);
    geStars(4);     // twelve stars: one egg

    // The other request reads the same count and hatches its egg on the turtles' island just before this one writes.
    $inner = app(GameCatalog::class);
    $meanwhile = function () {
        static $done = false;
        if (! $done) {
            $done = true;
            expect(app(HatchCreature::class)->byStars(test()->student->id, 'turtles')?->islandKey)->toBe('turtles');
        }
    };
    app()->instance(GameCatalog::class, new class($inner, $meanwhile) implements GameCatalog
    {
        public function __construct(private readonly GameCatalog $inner, private readonly Closure $meanwhile) {}

        public function islands(): array
        {
            ($this->meanwhile)();

            return $this->inner->islands();
        }

        public function games(): array
        {
            return $this->inner->games();
        }

        public function game(string $idOrKey): ?CatalogGame
        {
            return $this->inner->game($idOrKey);
        }

        public function creature(string $key): ?CatalogCreature
        {
            return $this->inner->creature($key);
        }
    });

    expect(app(HatchCreature::class)->byStars($this->student->id, 'birds'))->toBeNull()
        ->and(CreatureFind::query()->where('student_id', $this->student->id)->where('source', 'stars')->pluck('creature_key')->all())
        ->toHaveCount(1)->each->toStartWith('turtles.');

    // The next twelve stars hatch the next egg as usual.
    app()->forgetInstance(GameCatalog::class);
    app()->alias(PlatformGameCatalog::class, GameCatalog::class);
    geStars(4);
    expect(app(HatchCreature::class)->byStars($this->student->id, 'birds')?->islandKey)->toBe('birds')
        ->and(app(HatchCreature::class)->byStars($this->student->id, 'birds'))->toBeNull()
        ->and(CreatureFind::query()->where('source', 'stars')->count())->toBe(2);
});

it('opens the week\'s chest once when two opens pass the check at the same moment, held by the database', function () {
    $this->actAsTenant($this->academy);
    app(AttendanceApi::class)->participantJoined($this->tuesday->id, $this->student->id, $this->tuesday->starts_at->addMinute());
    $this->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized($this->tuesday->academy_id, $this->tuesday->id, 'completed', []));
    $homework = geHomework('سمّع سورة الفلق', CarbonImmutable::parse('2026-10-08 18:00', 'Africa/Cairo'), 'any');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'سمّعتها', null);

    // Between this open's check and its write, the other open (another phone) writes and commits.
    $other = geOtherRequest($this->academy->id);
    ChallengeClaim::creating(function () use ($other) {
        static $done = false;
        if (! $done) {
            $done = true;
            $other->table('challenge_claims')->insert([
                'id' => (string) Str::uuid7(), 'academy_id' => test()->academy->id, 'student_id' => test()->student->id,
                'challenge' => 'chest', 'period' => '2026-10-03', 'points' => 10, 'sticker' => 'lion', 'claimed_at' => CarbonImmutable::now(),
            ]);
        }
    });

    expect(fn () => app(OpenWeeklyChest::class)->handle($this->student->id, 'kids', '2026-10-03'))
        ->toThrow(ValidationException::class, __('learning::map.chest.opened'));
    expect(ChallengeClaim::query()->where('student_id', $this->student->id)->where('challenge', 'chest')->count())->toBe(1);
});

it('lets only the child tap the egg open, as on the family screens', function () {
    $this->actAsTenant($this->academy);
    geStars(4);

    $this->withToken(geToken($this->guardian))->postJson('/api/v1/games/hatch', ['learner_id' => $this->student->id, 'island' => 'birds'], ['Idempotency-Key' => 'g'])
        ->assertForbidden();
    $this->actAsTenant($this->academy);
    expect(CreatureFind::query()->count())->toBe(0);

    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);
    $this->withToken(geToken($this->student))->postJson('/api/v1/games/hatch', ['island' => 'birds'], ['Idempotency-Key' => 's'])
        ->assertCreated()->assertJsonPath('data.creature.island', 'birds');
});

it('plays a round on a station the learner may practise, scoring only the round\'s own items on their first try', function () {
    $this->actAsTenant($this->academy);
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', station: $this->lessons[0]);
    $ids = array_column($r['round'], 'id');
    expect($ids)->toHaveCount(3)->and($r['play']->island_key)->toBe('birds');

    // Another lesson's item, a made-up one, a second try that was right, and one item never tried.
    $foreign = DB::table('node_items')->where('node_id', '!=', $this->lessons[0])->value('id') ?? (string) Str::uuid();
    $done = app(FinishPlay::class)->handle($r['play'], $this->student->id, [
        ['item' => $ids[0], 'correct' => false], ['item' => $ids[0], 'correct' => true],
        ['item' => $ids[1], 'correct' => true],
        ['item' => $foreign, 'correct' => true], ['item' => 'made-up', 'correct' => true],
    ]);
    expect($done['play'])->toMatchArray(['correct' => 1, 'total' => 3, 'stars' => 1])
        // Tries at anything not in the round are not kept.
        ->and(collect($done['play']->answers)->pluck('item')->unique()->values()->all())->toBe([$ids[0], $ids[1]]);
    expect(DB::table('practice_stats')->where('node_id', $this->lessons[0])->first())
        ->toMatchObject(['rounds' => 1, 'answered' => 2, 'correct' => 1]);

    // Finishing again answers the same and counts nothing more.
    $again = app(FinishPlay::class)->handle($r['play']->refresh(), $this->student->id, playedAllRight($ids));
    expect($again['play']->stars)->toBe(1)->and(PracticeRoundEntry::query()->count())->toBe(1);

    // Only the learner's own journey: a lesson still ahead, another course's lesson, or nothing at all is not a station.
    foreach ([$this->lessons[6], (string) Str::uuid()] as $station) {
        expect(fn () => app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', station: $station))
            ->toThrow(fn (ProblemException $e) => expect($e->getStatusCode())->toBe(404));
    }
    expect(GamePlay::query()->count())->toBe(1);
});

function playedAllRight(array $ids): array
{
    return array_map(fn (string $id) => ['item' => $id, 'correct' => true], $ids);
}

it('counts the day\'s minutes and the night on the learner\'s clock, across midnight and the clocks going back', function () {
    $this->actAsTenant($this->academy);
    $allowance = app(PlayAllowance::class);
    $round = fn (string $utc, int $seconds) => GamePlay::query()->create([
        'student_id' => $this->student->id, 'played_by' => $this->student->id, 'game_id' => (string) Str::uuid(), 'game_key' => 'bird-balloons',
        'version_id' => (string) Str::uuid(), 'focus' => 'free', 'items' => [['id' => 'x', 'node' => $this->lessons[0], 'kind' => 'choice']],
        'answers' => [], 'started_at' => CarbonImmutable::parse($utc, 'UTC'), 'finished_at' => CarbonImmutable::parse($utc, 'UTC')->addSeconds($seconds),
        'seconds' => $seconds, 'correct' => 1, 'total' => 1, 'stars' => 3,
    ]);

    // London, Sunday 25 October 2026: the clocks go back at 02:00 BST. The day began at 00:00 BST (23:00 UTC the day before).
    $round('2026-10-24 22:30', 600);    // 23:30 on Saturday in London: yesterday's
    $round('2026-10-24 23:30', 600);    // 00:30 on Sunday: today's
    $round('2026-10-25 01:30', 300);    // 01:30 GMT, the hour that came twice
    expect($allowance->for($this->student->id, CarbonImmutable::parse('2026-10-25 12:00', 'UTC')))
        ->toMatchArray(['used' => 15, 'limit' => 30, 'left' => 15, 'may' => true]);
    // Monday on the learner's clock starts afresh.
    expect($allowance->for($this->student->id, CarbonImmutable::parse('2026-10-26 00:00', 'UTC'))['used'])->toBe(0);

    // The academy allows 30, the guardian 20 and the night lock: the lower one counts.
    app(SaveGameLimit::class)->handle($this->student->id, $this->guardian->id, true, 20, true);
    $at = fn (string $utc) => $allowance->for($this->student->id, CarbonImmutable::parse($utc, 'UTC'));
    expect($at('2026-10-25 12:00'))->toMatchArray(['limit' => 20, 'left' => 5, 'may' => true])
        ->and($at('2026-10-24 20:30'))->toMatchArray(['reason' => 'night'])    // 21:30 BST
        ->and($at('2026-10-25 20:30'))->toMatchArray(['may' => true])          // 20:30 GMT, an hour later in UTC terms
        ->and($at('2026-10-25 21:00'))->toMatchArray(['reason' => 'night'])
        ->and($at('2026-10-26 06:59'))->toMatchArray(['reason' => 'night'])
        ->and($at('2026-10-26 07:00'))->toMatchArray(['may' => true]);

    // The academy turns the games off: that says why first.
    app(SaveGameSettings::class)->handle($this->admin->id, false, 30);
    expect($at('2026-10-25 21:30')['reason'])->toBe('off');
});

it('starts a day where midnight never comes at the hour the clocks jump to', function () {
    $this->actAsTenant($this->academy);
    DB::table('users')->where('id', $this->student->id)->update(['timezone' => 'Africa/Cairo']);
    // Cairo, Friday 30 April 2027: 00:00 becomes 01:00 (EEST), which is 22:00 UTC on Thursday.
    foreach (['2027-04-29 21:50' => 600, '2027-04-29 22:05' => 300] as $utc => $seconds) {
        GamePlay::query()->create([
            'student_id' => $this->student->id, 'played_by' => $this->student->id, 'game_id' => (string) Str::uuid(), 'game_key' => 'bird-balloons',
            'version_id' => (string) Str::uuid(), 'focus' => 'free', 'items' => [['id' => 'x', 'node' => $this->lessons[0], 'kind' => 'choice']],
            'answers' => [], 'started_at' => CarbonImmutable::parse($utc, 'UTC'), 'seconds' => $seconds, 'total' => 1,
        ] + ['finished_at' => CarbonImmutable::parse($utc, 'UTC')->addSeconds($seconds), 'correct' => 1, 'stars' => 2]);
    }

    expect(app(PlayAllowance::class)->for($this->student->id, CarbonImmutable::parse('2027-04-30 09:00', 'UTC'))['used'])->toBe(5);
});

it('plays the homework a journey offers however late it is, and whatever else is open', function () {
    $this->actAsTenant($this->academy);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 09:00', 'UTC'));
    // Five days late (the journey still shows it), and four more open after it.
    $late = geHomework('الفاتحة باللعب', CarbonImmutable::now()->subDays(5));
    $late->update(['status' => 'missed']);
    $open = [];
    foreach ([1, 2, 3, 4] as $d) {
        $open[] = geHomework("جولة {$d}", CarbonImmutable::now()->addDays($d));
    }

    foreach ([$late, end($open)] as $homework) {
        $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', homeworkId: $homework->id);
        expect($r['play']->homework_id)->toBe($homework->id)->and($r['focus']->reason)->toBe('homework');
        $done = app(FinishPlay::class)->handle($r['play'], $this->student->id, playedAllRight(array_column($r['round'], 'id')));
        expect($done['handed_in'])->toBeTrue()->and($homework->refresh()->status)->toBe('submitted');
    }

    // Marked homework, or someone else's, does not start a round.
    $late->update(['status' => 'reviewed']);
    expect(fn () => app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', homeworkId: $late->id))
        ->toThrow(fn (ProblemException $e) => expect($e->getStatusCode())->toBe(404))
        ->and(fn () => app(StartPlay::class)->handle($this->other->id, $this->other->id, 'bird-balloons', homeworkId: $open[0]->id))
        ->toThrow(ProblemException::class);
});

it('takes only a game the learner can play for a lesson\'s homework', function () {
    $this->actAsTenant($this->academy);
    app(AttendanceApi::class)->participantJoined($this->tuesday->id, $this->student->id, $this->tuesday->starts_at->addMinute());
    $this->tuesday->update(['status' => 'completed']);
    event(new SessionFinalized($this->tuesday->academy_id, $this->tuesday->id, 'completed', []));
    CarbonImmutable::setTestNow($this->tuesday->starts_at->addHours(2));
    $report = SessionReport::query()->where('session_id', $this->tuesday->id)->firstOrFail();
    app(SubmitSessionReport::class)->handle($report, $this->teacher->id, [
        'engagement' => 5, 'overall' => 5, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced', 'score' => 4]],
    ]);

    // One pair on the lesson is not a round of Nest Twins (three at least); the gates play items in order, which it has none of.
    foreach (['bird-twins', 'bird-gates'] as $game) {
        expect(fn () => app(ChoosePracticeGame::class)->handle($report->refresh(), $this->teacher->id, $game, true, true))
            ->toThrow(ValidationException::class, __('learning::homework.game.errors.no_fit'));
    }
    // A teenager's look has no Turtle Bridge.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, null, LearnerStage::Teen);
    expect(fn () => app(ChoosePracticeGame::class)->handle($report->refresh(), $this->teacher->id, 'turtle-bridge', true, true))
        ->toThrow(ValidationException::class, __('learning::homework.game.errors.no_fit'));
    expect(Homework::query()->count())->toBe(0);

    $homework = app(ChoosePracticeGame::class)->handle($report->refresh(), $this->teacher->id, 'bird-balloons', true, true);
    expect($homework->practice_game)->toBe('bird-balloons')
        ->and(fn () => app(ChoosePracticeGame::class)->handle($report->refresh(), $this->admin->id, 'bird-balloons', true, true))
        ->toThrow(AuthorizationException::class);
});

it('still counts a homework\'s round when the teacher took the homework back while it was being played', function () {
    $this->actAsTenant($this->academy);
    $homework = geHomework('الفاتحة باللعب', CarbonImmutable::now()->addDays(2));
    $r = app(StartPlay::class)->handle($this->student->id, $this->student->id, 'bird-balloons', homeworkId: $homework->id);
    $homework->delete();

    $done = app(FinishPlay::class)->handle($r['play'], $this->student->id, playedAllRight(array_column($r['round'], 'id')));
    expect($done)->toMatchArray(['handed_in' => false, 'homework' => null])
        ->and($done['play']->stars)->toBe(3)
        ->and(PracticeRoundEntry::query()->sole())->toMatchArray(['homework_id' => null, 'stars' => 3]);
});
