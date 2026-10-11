<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Communication\Models\Notification;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Filament\Family\JourneyBoard;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The game layer (docs/architecture/02, "التحديات"): each stage's challenges
 * are counted from what really happened, only the student collects a reward
 * (once), rewards add to the points and the rank, the kids' chest brings a
 * sticker, and guardians cheer their child on. The fixture's week is Saturday
 * 3 to Friday 9 October on the student's clock (London): one lesson, Tuesday
 * 6 October 18:00 Cairo.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->stranger = User::factory()->withRole(Role::Guardian)->create();
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function challengesApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

function lookOf(User $student, LearnerStage $stage): void
{
    app(PeopleApi::class)->saveStudentProfile($student->id, null, $stage);
    app()->forgetInstance(FamilyView::class);
}

/** Tuesday's lesson happens: the student comes in $lateMin after the start, and the teacher reports it. */
function tuesdayTaught(int $lateMin = 1, string $outcome = 'completed', array $items = []): void
{
    lessonTaught(test()->tuesday, $lateMin, $outcome, $items);
}

function lessonTaught(Session $lesson, int $lateMin = 1, string $outcome = 'completed', array $items = []): void
{
    if ($outcome === 'completed') {
        app(AttendanceApi::class)->participantJoined($lesson->id, test()->student->id, $lesson->starts_at->addMinutes($lateMin));
    }
    $lesson->update(['status' => $outcome]);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, $outcome, []));
    if ($items !== []) {
        app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $lesson->id)->firstOrFail(), test()->teacher->id, [
            'engagement' => 5, 'overall' => 5,
            'items' => array_map(fn ($i) => ['node_id' => test()->lessons[$i[0]], 'outcome' => $i[1], 'score' => $i[2] ?? null], $items),
        ]);
    }
}

function homeworkDueThursday(bool $handIn): void
{
    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => test()->teacher->id, 'student_id' => test()->student->id, 'enrollment_id' => test()->booked->enrollmentId,
        'title' => 'سمّع سورة الفلق', 'due_at' => CarbonImmutable::parse('2026-10-08 18:00', 'Africa/Cairo'),
    ]);
    if ($handIn) {
        app(SubmitHomework::class)->handle($homework, test()->student->id, 'سمّعتها', null);
    }
}

/** @return array<string, array<string, mixed>> this week's challenges by key */
function thisWeek(array $data): array
{
    return collect($data['challenges'])->where('group', 'week')->keyBy('key')->all();
}

it('gives a child three missions and a chest, counted from what happened, then the reward once', function () {
    lookOf($this->student, LearnerStage::Kids);
    tuesdayTaught(lateMin: 1);
    homeworkDueThursday(handIn: true);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $token = challengesApp($this->student);

    $data = $this->withToken($token)->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/learning/challenges')
        ->assertOk()->assertJsonPath('data.stage', 'kids')->assertJsonPath('data.can_claim', true)->json('data');
    $week = thisWeek($data);
    expect(array_keys($week))->toBe(['attend', 'homework', 'on_time', 'chest'])
        ->and($week['attend'])->toMatchArray(['period' => '2026-10-03', 'target' => 1, 'progress' => 1, 'state' => 'done', 'claimable' => true, 'reward' => 50, 'icon' => 'calendar'])
        ->and($week['attend']['title'])->toBe(__('learning::challenges.kids.attend', [], 'ar')[0])
        ->and($week['on_time'])->toMatchArray(['target' => 1, 'progress' => 1, 'state' => 'done'])
        ->and($week['homework'])->toMatchArray(['target' => 1, 'progress' => 1, 'state' => 'done'])
        ->and($week['chest'])->toMatchArray(['target' => 3, 'progress' => 3, 'state' => 'done', 'sticker' => ['key' => 'lion', 'name' => __('learning::challenges.stickers.lion', [], 'ar')]])
        ->and($data['stickers']['earned'])->toBe(0);

    $points = $data['points'];
    $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'attend', 'period' => '2026-10-03'], ['Idempotency-Key' => 'one'])
        ->assertCreated()
        ->assertJsonPath('data.reward', 50)
        ->assertJsonPath('data.points_before', $points)
        ->assertJsonPath('data.points', $points + 50);

    // The app retrying the same tap gets the same answer; a second collection is refused.
    $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'attend', 'period' => '2026-10-03'], ['Idempotency-Key' => 'one'])
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'attend', 'period' => '2026-10-03'], ['Idempotency-Key' => 'two'])
        ->assertUnprocessable()->assertJsonPath('errors.challenge.0', __('learning::challenges.errors.claimed'));

    $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'chest', 'period' => '2026-10-03'], ['Idempotency-Key' => 'chest'])
        ->assertCreated()->assertJsonPath('data.sticker.key', 'lion')->assertJsonPath('data.points', $points + 100);

    $after = $this->withToken($token)->getJson('/api/v1/learning/challenges')->json('data');
    expect(thisWeek($after)['attend']['state'])->toBe('claimed')
        ->and(thisWeek($after)['chest']['state'])->toBe('claimed')
        ->and($after['stickers']['earned'])->toBe(1)
        ->and($after['stickers']['items'][0])->toMatchArray(['key' => 'lion', 'earned' => true])
        ->and($after['stickers']['items'][1])->toMatchArray(['key' => 'rocket', 'earned' => false])
        ->and($after['points'])->toBe($points + 100);

    // The journey counts the rewards too.
    $this->withToken($token)->getJson('/api/v1/learning/journey')->assertJsonPath('data.points', $points + 100);
});

it('does not count a late arrival or a missed lesson, and refuses what is not finished', function () {
    lookOf($this->student, LearnerStage::Kids);
    tuesdayTaught(outcome: 'student_absent');
    homeworkDueThursday(handIn: false);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 12:00', 'UTC'));   // after Thursday's due time
    $token = challengesApp($this->student);

    $week = thisWeek($this->withToken($token)->getJson('/api/v1/learning/challenges')->json('data'));
    expect($week['attend'])->toMatchArray(['progress' => 0, 'state' => 'missed', 'claimable' => false])
        ->and($week['homework']['state'])->toBe('missed')
        ->and($week['on_time']['state'])->toBe('missed')
        ->and($week['chest'])->toMatchArray(['progress' => 0, 'state' => 'active']);

    $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'attend', 'period' => '2026-10-03'], ['Idempotency-Key' => 'x'])
        ->assertUnprocessable()->assertJsonPath('errors.challenge.0', __('learning::challenges.errors.not_ready'));
});

it('gives middle school quests and a boss for each level, and moves the rank up', function () {
    lookOf($this->student, LearnerStage::Middle);
    // Level 1 is lessons 1–5; lesson 3 needs two scores of 4+, the second on Saturday.
    tuesdayTaught(items: [[0, 'mastered'], [1, 'mastered'], [2, 'practiced', 5], [3, 'mastered'], [4, 'mastered']]);
    $saturday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();
    lessonTaught($saturday, items: [[2, 'practiced', 5]]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 09:00', 'UTC'));   // the week of Saturday 10 October
    $token = challengesApp($this->student);

    $data = $this->withToken($token)->getJson('/api/v1/learning/challenges')->assertJsonPath('data.stage', 'middle')->json('data');
    $week = thisWeek($data);
    expect(array_keys($week))->toBe(['attend', 'master', 'focus'])                 // no homework this week
        ->and($week['attend'])->toMatchArray(['target' => 2, 'progress' => 1, 'state' => 'active'])
        ->and($week['master'])->toMatchArray(['target' => 1, 'progress' => 1, 'state' => 'done', 'reward' => 120])
        ->and($week['focus'])->toMatchArray(['target' => 2, 'progress' => 1, 'state' => 'active']);

    // Last week's finished quests and the finished level wait to be collected, before this week's.
    $groups = collect($data['challenges'])->pluck('group')->all();
    expect(array_slice($groups, 0, 4))->toBe(['last_week', 'last_week', 'last_week', 'world'])
        ->and(collect($data['challenges'])->where('group', 'last_week')->pluck('key')->all())->toBe(['attend', 'master', 'focus']);
    $boss = collect($data['challenges'])->firstWhere('key', 'boss');
    expect($boss)->toMatchArray(['group' => 'world', 'state' => 'done', 'reward' => 300, 'icon' => 'crown'])
        ->and($boss['world']['index'])->toBe(1);
    expect(collect($data['challenges'])->where('key', 'boss')->where('world.index', 2)->first())->toMatchArray(['state' => 'active', 'target' => 2, 'progress' => 0]);

    $before = $data['rank']['key'];
    $claimed = $this->withToken($token)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'boss', 'period' => $boss['period']], ['Idempotency-Key' => 'boss'])
        ->assertCreated()->json('data');
    expect($claimed['points'])->toBe($data['points'] + 300)
        ->and($claimed['ranked_up'])->toBe($claimed['rank']['key'] !== $before)
        ->and($claimed['rank']['key'])->not->toBe('beginner');
    expect(collect($this->withToken($token)->getJson('/api/v1/learning/challenges')->json('data.challenges'))->where('key', 'boss')->where('world.index', 1))->toBeEmpty();
});

it('gives teens and adults weekly goals and a month without missing', function () {
    lookOf($this->student, LearnerStage::Teen);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $data = $this->withToken(challengesApp($this->student))->getJson('/api/v1/learning/challenges')->json('data');

    expect($data['stage'])->toBe('teen')
        ->and(collect($data['challenges'])->pluck('key')->all())->toBe(['attend', 'master', 'month'])
        ->and(collect($data['challenges'])->firstWhere('key', 'month'))->toMatchArray(['period' => '2026-10', 'target' => 8, 'progress' => 0, 'state' => 'active'])
        ->and($data['stickers'])->toBeNull();
});

it('lets a guardian watch and cheer, never collect; the child sees each cheer once', function () {
    lookOf($this->student, LearnerStage::Kids);
    tuesdayTaught();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $guardian = challengesApp($this->guardian);

    $this->withToken($guardian)->getJson('/api/v1/learning/challenges?learner_id='.$this->student->id)
        ->assertOk()->assertJsonPath('data.can_claim', false)->assertJsonPath('data.stage', 'kids')
        ->assertJsonPath('data.challenges.0.claimable', false)->assertJsonPath('data.cheers_left', 5)->assertJsonPath('data.cheers', []);
    $this->withToken($guardian)->postJson('/api/v1/learning/challenges/claim', ['challenge' => 'attend', 'period' => '2026-10-03'], ['Idempotency-Key' => 'g'])
        ->assertForbidden();

    $this->withToken($guardian)->postJson('/api/v1/learning/cheers', ['learner_id' => $this->student->id, 'kind' => 'clap'])->assertCreated();
    $this->actAsTenant($this->academy);
    expect(Notification::query()->where('user_id', $this->student->id)->where('template_key', 'learning.cheer')->exists())->toBeTrue();
    $this->clearTenant();

    $student = challengesApp($this->student);
    $cheers = $this->withToken($student)->getJson('/api/v1/learning/challenges')->json('data.cheers');
    expect($cheers)->toHaveCount(1)->and($cheers[0])->toMatchArray(['kind' => 'clap', 'from' => strtok($this->guardian->name, ' ')]);
    $this->withToken($student)->postJson('/api/v1/learning/cheers/seen')->assertNoContent();
    $this->withToken($student)->getJson('/api/v1/learning/challenges')->assertJsonPath('data.cheers', []);

    // A few a day; only for their own child; a student cannot cheer themself.
    $guardian = challengesApp($this->guardian);
    foreach (range(1, 4) as $_) {
        $this->withToken($guardian)->postJson('/api/v1/learning/cheers', ['learner_id' => $this->student->id, 'kind' => 'star'])->assertCreated();
    }
    $this->withToken($guardian)->postJson('/api/v1/learning/cheers', ['learner_id' => $this->student->id, 'kind' => 'star'])
        ->assertUnprocessable()->assertJsonPath('errors.kind.0', __('learning::challenges.errors.too_many_cheers'));
    $this->withToken(challengesApp($this->stranger))->postJson('/api/v1/learning/cheers', ['learner_id' => $this->student->id, 'kind' => 'heart'])
        ->assertUnprocessable();
    $this->withToken(challengesApp($this->stranger))->getJson('/api/v1/learning/challenges?learner_id='.$this->student->id)->assertNotFound();
});

it('keeps the cheers on the family home\'s journey: the guardian cheers, the child sees it once', function () {
    lookOf($this->student, LearnerStage::Kids);
    tuesdayTaught();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('family');

    $this->actingAs($this->guardian);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(JourneyBoard::class)
        ->assertSee(trans_choice('learning::map.cheers.left', 5, ['count' => 5]))
        ->call('cheer', 'heart')
        ->assertSee(trans_choice('learning::map.cheers.left', 4, ['count' => 4]));
    expect(Cheer::query()->where('student_id', $this->student->id)->where('kind', 'heart')->exists())->toBeTrue()
        ->and(ChallengeClaim::query()->exists())->toBeFalse();

    $this->actingAs($this->student);
    app()->forgetInstance(FamilyView::class);
    Livewire::test(JourneyBoard::class)
        ->assertSee(__('learning::map.cheers.from', ['name' => strtok($this->guardian->name, ' '), 'kind' => __('learning::challenges.cheers.heart')]))
        ->call('cheersSeen');
    Livewire::test(JourneyBoard::class)
        ->assertDontSee(__('learning::map.cheers.from', ['name' => strtok($this->guardian->name, ' '), 'kind' => __('learning::challenges.cheers.heart')]));
    expect(Cheer::query()->whereNull('seen_at')->exists())->toBeFalse();
});
