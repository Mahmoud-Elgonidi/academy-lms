<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\MeterRoom;
use Modules\Classroom\Actions\MeterRooms;
use Modules\Classroom\Models\MeetingLink;
use Modules\Classroom\Models\Room;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Metering\GrantUsage;
use Modules\Platform\Actions\Metering\SaveAiCreditRates;
use Modules\Platform\Actions\Metering\SaveUsageAlerts;
use Modules\Platform\Actions\Metering\SetVideoHardLimit;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Platform\Actions\TopUps\ActivateTopUp;
use Modules\Platform\Actions\TopUps\RequestTopUp;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AiCreditRatesPage;
use Modules\Platform\Filament\Pages\UsagePage;
use Modules\Platform\Metering\MeteringSweep;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Metering\NotEnoughBalance;
use Modules\Platform\Metering\PendingCharges;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\PlanWarning;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Http\ProblemDetails;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Events\BalanceRunningOut;
use Modules\SharedKernel\Plans\Events\LotExpiringSoon;
use Modules\SharedKernel\Plans\Events\UsageThresholdReached;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Symfony\Component\Process\Process;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The usage ledger (docs/architecture/02, section 11): each academy's video
 * minutes and AI credits, the month's allowance spent first and then the
 * bought lots ending soonest; AI credits held before a request and settled
 * after, nothing for a failure; a room's actual minutes; soft overage the
 * next balance covers, and the optional hard limit; alerts once each; the
 * academy's page and the console's tools.
 *
 * The fixture (EnrolledStudent): academy "noor" (Africa/Cairo) with an admin,
 * two teachers and an adult student enrolled, Monday 5 October 2026; the first
 * lesson is Tuesday 6 October 15:00 UTC, 30 minutes.
 */

beforeEach(function () {
    Mail::fake();
    $this->enrolStudentOnPlan();
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['email' => 'owner@noor.test', 'locale' => 'ar']);
    $this->platformOwner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->platformOwner->forceFill(['is_owner' => true])->save();
    $this->helperAdmin = PlatformAdmin::query()->create(['name' => 'Omar', 'email' => 'omar@platform.test', 'password' => 'a-long-password']);
    config(['platform.ai.driver' => 'demo', 'platform.ai.daily_cap' => 500]);
    app()->setLocale('ar');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function meteredPlan(array $attributes = [], array $limits = []): SaasPlan
{
    $plan = SaasPlan::query()->create($attributes + ['code' => 'm-'.Str::lower(Str::random(6)), 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => ['ai']]);
    app(ChangeAcademyPlan::class)->handle(test()->academy->id, $plan->id, 'For this test', $limits);
    app(PlanWarning::class)->forget(test()->academy->id);

    return $plan;
}

function usageLedger(): Meters
{
    return app(Meters::class);
}

function aiOn(): void
{
    app(SaveAiSettings::class)->handle(['practice_items' => true, 'report_draft' => true, 'digest_wording' => true, 'curriculum_outline' => true, 'recitation_check' => true], test()->owner->id);
}

function aiPrompt(): AiPrompt
{
    return new AiPrompt('Answer.', 'Two items.', ['type' => 'object'], demo: ['items' => ['a']]);
}

/** A recording on disk of about this many seconds (64 kbit/s). */
function usageRecording(int $seconds = 40): AudioClip
{
    $path = tempnam(sys_get_temp_dir(), 'clip');
    file_put_contents($path, str_repeat('a', $seconds * 8000));

    return new AudioClip($path, 'audio/webm');
}

/** @return list<string> the kinds written, oldest first */
function ledgerKinds(Meter $meter): array
{
    return DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', test()->academy->id)->where('meter', $meter->value)
        ->orderBy('created_at')->orderBy('id')->pluck('kind')->all();
}

function usageFirstLesson(): Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->first();
}

function roomFor(Session $lesson, ?string $started, ?string $finished, string $provider = 'livekit'): Room
{
    return Room::query()->create(['session_id' => $lesson->id, 'provider' => $provider, 'name' => Room::nameFor(test()->academy->id, $lesson->id),
        'started_at' => $started === null ? null : CarbonImmutable::parse($started), 'finished_at' => $finished === null ? null : CarbonImmutable::parse($finished)]);
}

it('spends this month\'s allowance first, then the bought lots ending soonest, writes off what ended, and covers overage from the next balance', function () {
    meteredPlan(['video_minutes' => 100]);
    $id = $this->academy->id;
    $soon = usageLedger()->addLot($id, Meter::VideoMinutes, Ledger::PURCHASE, 50, CarbonImmutable::now()->addMonths(2));
    $late = usageLedger()->addLot($id, Meter::VideoMinutes, Ledger::PURCHASE, 50, CarbonImmutable::now()->addMonths(12));

    usageLedger()->consume($id, Meter::VideoMinutes, 130, ['subject' => 'lesson']);
    $state = usageLedger()->state($id, Meter::VideoMinutes);
    expect($state->allowance)->toBe(100)->and($state->allowanceLeft)->toBe(0)->and($state->bought)->toBe(70)
        ->and(collect($state->lots)->pluck('left', 'id')->all())->toBe([$soon => 20, $late => 50])
        ->and($state->used)->toBe(130)->and($state->available())->toBe(70);

    // Beyond everything the lessons go on: overage, owed.
    usageLedger()->consume($id, Meter::VideoMinutes, 90, ['subject' => 'lesson']);
    $state = usageLedger()->state($id, Meter::VideoMinutes);
    expect($state->bought)->toBe(0)->and($state->debt)->toBe(20)->and($state->available())->toBe(-20);
    $line = collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::VideoMinutes);
    expect($line->used)->toBe(220)->and($line->state())->toBe('full');

    // Next month on the academy's clock: its allowance comes in and covers the overage first; last month's is gone.
    $this->travelTo(CarbonImmutable::parse('2026-11-01 00:10', 'Africa/Cairo'));
    $state = usageLedger()->state($id, Meter::VideoMinutes);
    expect($state->period)->toBe('2026-11-01')->and($state->allowance)->toBe(100)->and($state->debt)->toBe(0)
        ->and($state->allowanceLeft)->toBe(80)->and($state->used)->toBe(0);

    // A lot that ended is written off once, with what was left of it.
    $bonus = usageLedger()->addLot($id, Meter::VideoMinutes, Ledger::BONUS, 30, CarbonImmutable::now()->addDay(), ['reason' => 'A gift', 'ref_type' => 'console']);
    $this->travelTo(CarbonImmutable::now()->addDays(2));
    expect(usageLedger()->expireLots($id, Meter::VideoMinutes))->toBe(1)->and(usageLedger()->expireLots($id, Meter::VideoMinutes))->toBe(0);
    expect((int) DB::connection('pgsql_platform')->table('usage_ledger')->where('lot_id', $bonus)->where('kind', 'expire')->value('amount'))->toBe(-30);

    // The ledger is kept as written.
    expect(fn () => DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $id)->limit(1)->update(['amount' => 1]))->toThrow(QueryException::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $id)->delete())->toThrow(QueryException::class);
});

it('adds a bigger plan\'s difference within the month and takes nothing back for a smaller one', function () {
    meteredPlan(['video_minutes' => 100]);
    expect(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->allowance)->toBe(100);
    meteredPlan(['video_minutes' => 300]);
    expect(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->allowance)->toBe(300);
    meteredPlan(['video_minutes' => 50]);
    expect(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->allowance)->toBe(300);
    // No limit: nothing is held, and what is used is still noted for the page.
    meteredPlan([]);
    usageLedger()->consume($this->academy->id, Meter::VideoMinutes, 12, ['subject' => 'lesson']);
    $state = usageLedger()->state($this->academy->id, Meter::VideoMinutes);
    expect($state->limited)->toBeFalse()->and($state->used)->toBe(12)->and($state->debt)->toBe(0)
        ->and(usageLedger()->reserve($this->academy->id, Meter::VideoMinutes, 1000))->toBeNull();
});

it('charges each helper its rate once, after its answer, and a failure costs nothing', function () {
    meteredPlan(['ai_uses' => 100]);
    aiOn();
    $this->actingAs($this->teacher);
    $model = app(LanguageModel::class);
    $speech = app(SpeechToText::class);

    $model->json(AiFeature::PracticeItems, aiPrompt());
    $model->json(AiFeature::DigestWording, aiPrompt());
    $model->json(AiFeature::CurriculumOutline, aiPrompt());
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->used)->toBe(7);

    // The recitation check: 2 a minute of what was heard (the demo hears half a second a word: one minute).
    $speech->transcribe(AiFeature::RecitationCheck, usageRecording());
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->used)->toBe(9);

    // A report from a voice note hears then writes: charged once, 2 and 1 for the minute, when the words come back.
    $speech->transcribe(AiFeature::ReportDraft, usageRecording());
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->used)->toBe(9)
        ->and(usageLedger()->state($this->academy->id, Meter::AiCredits)->held)->toBeGreaterThan(0);
    $model->json(AiFeature::ReportDraft, aiPrompt());
    $state = usageLedger()->state($this->academy->id, Meter::AiCredits);
    expect($state->used)->toBe(12)->and($state->held)->toBe(0);

    // Heard and never written from (too few words, say): nothing.
    $speech->transcribe(AiFeature::ReportDraft, usageRecording());
    app(PendingCharges::class)->releaseAll();
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits))->used->toBe(12)->held->toBe(0);

    // Each use names the helper and who asked.
    $rows = DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $this->academy->id)->where('kind', 'consume')->get();
    expect($rows->pluck('subject')->unique()->sort()->values()->all())->toBe(['curriculum_outline', 'digest_wording', 'practice_items', 'recitation_check', 'report_draft'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([$this->teacher->id]);

    // The console's rate counts from the next request.
    app(SaveAiCreditRates::class)->handle($this->platformOwner, ['practice_items' => ['base' => 3, 'per_minute' => 0]]);
    $model->json(AiFeature::PracticeItems, aiPrompt());
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->used)->toBe(15);
});

it('holds the estimate before a request and lets it go when the provider fails', function () {
    meteredPlan(['ai_uses' => 10]);
    aiOn();
    config(['platform.ai.driver' => 'openai', 'platform.ai.openai' => ['key' => 'sk-test', 'base_url' => 'https://ai.example.com/v1', 'models' => ['best' => 'm', 'fast' => 'm']]]);
    Http::fake(['ai.example.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'overloaded']], 503)
        ->push(['model' => 'm', 'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(['items' => ['a']])]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])]);

    expect(fn () => app(LanguageModel::class)->json(AiFeature::PracticeItems, aiPrompt()))->toThrow(AiFailed::class);
    expect(ledgerKinds(Meter::AiCredits))->toBe(['allowance', 'reserve', 'release'])
        ->and(usageLedger()->state($this->academy->id, Meter::AiCredits)->available())->toBe(10);

    app(LanguageModel::class)->json(AiFeature::PracticeItems, aiPrompt());
    expect(array_slice(ledgerKinds(Meter::AiCredits), 3))->toBe(['reserve', 'release', 'consume'])
        ->and(usageLedger()->state($this->academy->id, Meter::AiCredits)->available())->toBe(9);

    // A hold nobody settled lets go by itself, and the sweep writes its release.
    $hold = usageLedger()->reserve($this->academy->id, Meter::AiCredits, 4);
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->available())->toBe(5);
    $this->travelTo(CarbonImmutable::now()->addMinutes(31));
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->available())->toBe(9)
        ->and(usageLedger()->releaseStale($this->academy->id, Meter::AiCredits))->toBe(1)
        ->and(DB::connection('pgsql_platform')->table('usage_ledger')->where('hold_id', $hold)->pluck('kind')->sort()->values()->all())->toBe(['release', 'reserve']);
});

it('stops the helper at zero, says how many credits the request needed, and brings it back with a pack', function () {
    meteredPlan(['ai_uses' => 4]);
    aiOn();
    $ai = app(AcademyAi::class);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue();

    try {
        app(LanguageModel::class)->json(AiFeature::CurriculumOutline, aiPrompt());
        $this->fail('The outline costs 5 credits and only 4 are left.');
    } catch (AiFailed $e) {
        expect($e->reason)->toBe('quota')->and($e->needed)->toBe(5)->and($e->sayable())->toContain('5 نقاط')->toContain('يدويًا');
        $response = ProblemDetails::render($e, request()->create('/api/v1/curriculum/outline', 'POST', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar']));
        expect($response->getStatusCode())->toBe(429)->and($response->getData(true))->toMatchArray(['type' => 'ai-quota', 'reason' => 'quota', 'credits_needed' => 5]);
    }
    expect(usageLedger()->state($this->academy->id, Meter::AiCredits)->used)->toBe(0);

    foreach (range(1, 4) as $_) {
        app(LanguageModel::class)->json(AiFeature::PracticeItems, aiPrompt());
    }
    expect($ai->why(AiFeature::PracticeItems))->toBe('quota');

    // A bonus from the console: back at once.
    app(GrantUsage::class)->handle($this->platformOwner, $this->academy->id, ['kind' => 'bonus', 'meter' => 'ai_credits', 'amount' => 10, 'reason' => 'Launch gift']);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue();
});

it('lets two requests racing for the last credits take them once', function () {
    meteredPlan(['ai_uses' => 5]);
    $id = $this->academy->id;
    usageLedger()->ensureAllowance($id, Meter::AiCredits);

    // This test holds the academy's lock while two other processes ask for 4 of the 5 credits at once.
    $db = DB::connection('pgsql_platform');
    $db->beginTransaction();
    $db->select('select pg_advisory_xact_lock(hashtext(?))', [Meters::LOCK.$id.':ai_credits']);
    $script = base_path('tests/Fixtures/reserve-credits.php');
    $at = CarbonImmutable::now()->toIso8601String();
    $runs = [new Process([PHP_BINARY, $script, $id, '4', $at]), new Process([PHP_BINARY, $script, $id, '4', $at])];
    foreach ($runs as $run) {
        $run->start();
    }
    usleep(1500000);
    expect($runs[0]->isRunning())->toBeTrue()->and($runs[1]->isRunning())->toBeTrue();   // both wait for the lock
    $db->commit();
    foreach ($runs as $run) {
        $run->wait();
    }

    $answers = array_map(fn (Process $run) => trim($run->getOutput()), $runs);
    sort($answers);
    expect($answers)->toBe(['held', 'refused'], implode(' / ', array_map(fn (Process $r) => $r->getErrorOutput(), $runs)))
        ->and(usageLedger()->state($id, Meter::AiCredits)->held)->toBe(4);
    expect(fn () => usageLedger()->reserve($id, Meter::AiCredits, 2))->toThrow(NotEnoughBalance::class);
});

it('counts a room by its actual open time with the cap, once, nothing for an outside link, and the sweep finds the missed ones', function () {
    meteredPlan(['video_minutes' => 600]);
    $lesson = usageFirstLesson();   // 30 minutes, Tuesday 6 October 15:00 UTC
    $meter = app(MeterRoom::class);

    $room = roomFor($lesson, '2026-10-06 14:58:10', '2026-10-06 15:31:00');
    expect($meter->handle($room))->toBe(33)->and($meter->handle($room->refresh()))->toBe(33);
    expect(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->used)->toBe(33)
        ->and(DB::connection('pgsql_platform')->table('usage_ledger')->where('ref_type', 'room')->value('user_id'))->toBe($this->teacher->id);

    // Opened again and left open: counted up to the lesson and 15 minutes, only what it added.
    $room->update(['finished_at' => CarbonImmutable::parse('2026-10-06 18:00')]);
    expect($meter->handle($room->refresh()))->toBe(45)->and(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->used)->toBe(45);

    // The teacher's outside link costs nothing.
    $other = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->first();
    expect($meter->handle(roomFor($other, '2026-10-10 14:59', '2026-10-10 15:30', 'external')))->toBeNull();

    // A room whose close the webhook never told, once its lesson is long over: the sweep counts it to the class's end.
    $third = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(2)->first();
    $stuck = roomFor($third, $third->starts_at->subMinute()->toIso8601String(), null);
    $this->travelTo($third->ends_at->addHours(2));
    expect(app(MeterRooms::class)->handle())->toBe(1)->and($stuck->refresh()->metered_at)->not->toBeNull()
        ->and(app(MeterRooms::class)->handle())->toBe(0)
        ->and(usageLedger()->state($this->academy->id, Meter::VideoMinutes)->used)->toBe(45 + 41);
});

it('shows the rooms open now in the month\'s use until they close', function () {
    meteredPlan(['video_minutes' => 600]);
    $lesson = usageFirstLesson();
    roomFor($lesson, '2026-10-06 15:00', null);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 15:12:30'));

    $line = collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::VideoMinutes);
    expect($line->used)->toBe(13)->and($line->left())->toBe(587);
});

it('opens a new lesson on the teacher\'s link with a notice under the hard limit at zero, and never cuts one under way', function () {
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    meteredPlan(['video_minutes' => 30]);
    MeetingLink::query()->create(['teacher_id' => $this->teacher->id, 'url' => 'https://meet.example.com/abc']);
    usageLedger()->consume($this->academy->id, Meter::VideoMinutes, 30, ['subject' => 'lesson']);
    $lesson = usageFirstLesson();
    $at = CarbonImmutable::parse('2026-10-06 14:55 UTC');

    // Soft at first: our own video, the balance may go below nothing.
    expect(app(JoinLesson::class)->handle($lesson->id, $this->teacher->id, $at)->provider)->toBe('livekit');
    Room::query()->where('session_id', $lesson->id)->delete();

    expect(fn () => app(SetVideoHardLimit::class)->handle($this->helperAdmin, $this->academy->id, true, 'Agreed with them'))->toThrow(AuthorizationException::class);
    app(SetVideoHardLimit::class)->handle($this->platformOwner, $this->academy->id, true, 'Agreed with them');
    $ticket = app(JoinLesson::class)->handle($lesson->id, $this->teacher->id, $at);
    expect($ticket->provider)->toBe('external')->and($ticket->url)->toBe('https://meet.example.com/abc')
        ->and($ticket->toArray()['notice'])->toBe(__('classroom::classroom.video_held'));

    // A lesson already under way on our video stays there.
    $other = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->first();
    roomFor($other, $other->starts_at->toIso8601String(), null);
    expect(app(JoinLesson::class)->handle($other->id, $this->teacher->id, $other->starts_at->addMinutes(5))->provider)->toBe('livekit');

    // Balance again: our own video.
    app(GrantUsage::class)->handle($this->platformOwner, $this->academy->id, ['kind' => 'bonus', 'meter' => 'video_minutes', 'amount' => 600, 'reason' => 'Bonus month']);
    Room::query()->where('session_id', $lesson->id)->delete();
    expect(app(JoinLesson::class)->handle($lesson->id, $this->teacher->id, $at)->provider)->toBe('livekit');

    expect(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.video_hard_limit_set')->count())->toBe(1);
});

it('raises each alert once: 50% (unless turned off), 80%, 100%, ending lots and the run-out', function () {
    meteredPlan(['video_minutes' => 100]);
    Event::fake([UsageThresholdReached::class, LotExpiringSoon::class, BalanceRunningOut::class]);
    $id = $this->academy->id;

    usageLedger()->consume($id, Meter::VideoMinutes, 55, ['subject' => 'lesson']);
    usageLedger()->consume($id, Meter::VideoMinutes, 1, ['subject' => 'lesson']);
    Event::assertDispatchedTimes(UsageThresholdReached::class, 1);
    usageLedger()->consume($id, Meter::VideoMinutes, 30, ['subject' => 'lesson']);
    usageLedger()->consume($id, Meter::VideoMinutes, 30, ['subject' => 'lesson']);
    Event::assertDispatched(UsageThresholdReached::class, fn (UsageThresholdReached $e) => $e->threshold === 100 && $e->allowance === 100 && $e->period === '2026-10-01');
    Event::assertDispatchedTimes(UsageThresholdReached::class, 3);
    app(MeteringSweep::class)->academy($id);
    Event::assertDispatchedTimes(UsageThresholdReached::class, 3);

    // Bought balance in use and running out at the pace: once; and ending within 30 days with some left: once.
    usageLedger()->addLot($id, Meter::VideoMinutes, Ledger::PURCHASE, 60, CarbonImmutable::now()->addDays(20));
    usageLedger()->consume($id, Meter::VideoMinutes, 30, ['subject' => 'lesson']);
    app(MeteringSweep::class)->academy($id);
    app(MeteringSweep::class)->academy($id);
    Event::assertDispatchedTimes(BalanceRunningOut::class, 1);
    Event::assertDispatchedTimes(LotExpiringSoon::class, 1);

    // The academy turns 50% off: next month it hears from 80%.
    $this->actingAs($this->owner);
    app(SaveUsageAlerts::class)->handle(false, $this->owner->id);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00', 'Africa/Cairo'));
    usageLedger()->consume($id, Meter::VideoMinutes, 60, ['subject' => 'lesson']);
    Event::assertDispatchedTimes(UsageThresholdReached::class, 3);
    usageLedger()->consume($id, Meter::VideoMinutes, 20, ['subject' => 'lesson']);
    Event::assertDispatched(UsageThresholdReached::class, fn (UsageThresholdReached $e) => $e->threshold === 80 && $e->period === '2026-11-01');
});

it('tells the staff who see the plan, in their inbox, never a teacher', function () {
    meteredPlan(['features' => ['ai'], 'ai_uses' => 10]);
    usageLedger()->consume($this->academy->id, Meter::AiCredits, 10, ['subject' => 'practice_items']);

    $titles = DB::table('notifications')->where('user_id', $this->owner->id)->orderBy('created_at')->pluck('title', 'template_key')->all();
    expect(array_keys($titles))->toBe(['plans.usage_half', 'plans.usage_near', 'plans.usage_full'])
        ->and($titles['plans.usage_full'])->toContain('نقاط المساعد الآلي')
        ->and(DB::table('notifications')->where('user_id', $this->teacher->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('template_key', 'plans.usage_full')->value('body'))->toContain('10 نقاط')->toContain('يدويًا');
});

it('lets only the academy\'s owner and admins choose the 50% alert, never while signed in as someone, audited', function () {
    meteredPlan(['video_minutes' => 100]);
    $this->actingAs($this->teacher);
    expect(fn () => app(SaveUsageAlerts::class)->handle(false, $this->teacher->id))->toThrow(AuthorizationException::class);
    $this->actingAs($this->owner);
    AuditActor::use(new AuditActor('platform', $this->platformOwner->id, 'سلمى', onBehalfOf: $this->owner->id));
    expect(fn () => app(SaveUsageAlerts::class)->handle(false, $this->owner->id))->toThrow(ValidationException::class);
    AuditActor::forget();
    app(SaveUsageAlerts::class)->handle(false, $this->owner->id);
    app(SaveUsageAlerts::class)->handle(false, $this->owner->id);

    expect(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.usage_alerts_saved')->count())->toBe(1);
});

it('lets the platform\'s owners grant, correct and refund with a reason, audited without it', function () {
    meteredPlan(['video_minutes' => 100]);
    $id = $this->academy->id;
    $lot = usageLedger()->addLot($id, Meter::VideoMinutes, Ledger::PURCHASE, 300, CarbonImmutable::now()->addYear(), ['ref_type' => 'topup', 'ref_id' => 'x']);
    $grant = fn (array $data, ?PlatformAdmin $by = null) => app(GrantUsage::class)->handle($by ?? $this->platformOwner, $id, $data + ['reason' => 'Agreed on the phone']);

    expect(fn () => $grant(['kind' => 'bonus', 'meter' => 'video_minutes', 'amount' => 50], $this->helperAdmin))->toThrow(AuthorizationException::class)
        ->and(fn () => $grant(['kind' => 'bonus', 'meter' => 'video_minutes', 'amount' => 50, 'reason' => 'no']))->toThrow(ValidationException::class)
        ->and(fn () => $grant(['kind' => 'refund', 'meter' => 'video_minutes', 'amount' => 301, 'lot' => $lot]))->toThrow(ValidationException::class);

    $grant(['kind' => 'bonus', 'meter' => 'video_minutes', 'amount' => 50, 'months' => 3]);
    $grant(['kind' => 'adjust', 'meter' => 'video_minutes', 'amount' => -20]);
    $grant(['kind' => 'refund', 'meter' => 'video_minutes', 'amount' => 300, 'lot' => $lot]);
    $state = usageLedger()->state($id, Meter::VideoMinutes);
    expect($state->allowanceLeft)->toBe(80)->and($state->bought)->toBe(50);

    $log = DB::connection('pgsql_platform')->table('audit_logs')->where('academy_id', $id)->where('action', 'like', 'academy.usage_%')->get();
    expect($log->pluck('action')->sort()->values()->all())->toBe(['academy.usage_adjusted', 'academy.usage_bonus', 'academy.usage_refunded'])
        ->and($log->pluck('data')->implode(' '))->not->toContain('Agreed')
        ->and(DB::connection('pgsql_platform')->table('usage_ledger')->where('kind', 'bonus')->value('reason'))->toBe('Agreed on the phone');

    // Rates: owners only, names in the log; a rate set back follows the capability again.
    expect(fn () => app(SaveAiCreditRates::class)->handle($this->helperAdmin, ['practice_items' => ['base' => 2]]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveAiCreditRates::class)->handle($this->platformOwner, ['practice_items' => ['base' => 0, 'per_minute' => 0]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveAiCreditRates::class)->handle($this->platformOwner, ['practice_items' => ['base' => 1, 'per_minute' => 2]]))->toThrow(ValidationException::class)
        ->and(app(SaveAiCreditRates::class)->handle($this->platformOwner, ['report_draft' => ['base' => 3, 'per_minute' => 2]]))->toBe(['report_draft'])
        ->and(app(SaveAiCreditRates::class)->handle($this->platformOwner, ['report_draft' => ['base' => 2, 'per_minute' => 1]]))->toBe(['report_draft'])
        ->and(DB::connection('pgsql_platform')->table('ai_credit_rates')->count())->toBe(0)
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.ai_credit_rates_saved')->count())->toBe(2);
});

it('turns an extra pack into a 12-month lot that covers overage first', function () {
    meteredPlan(['features' => ['ai'], 'ai_uses' => 10]);
    $this->actingAs($this->owner);
    usageLedger()->consume($this->academy->id, Meter::AiCredits, 14, ['subject' => 'practice_items']);
    $pack = TopUpPack::query()->create(['limit' => 'ai_uses', 'amount' => 240, 'name_ar' => 'صغيرة', 'name_en' => 'Small']);
    $topUp = app(RequestTopUp::class)->handle($pack->id, null, $this->owner->id);
    app(ActivateTopUp::class)->handle($this->platformOwner, $topUp->id);

    $state = usageLedger()->state($this->academy->id, Meter::AiCredits);
    expect($state->debt)->toBe(0)->and($state->bought)->toBe(236)
        ->and(abs($state->lots[0]['expires_at']->diffInMonths(CarbonImmutable::now())))->toBeGreaterThanOrEqual(11.9)
        ->and(fn () => app(ActivateTopUp::class)->handle($this->platformOwner, $topUp->id))->toThrow(ValidationException::class);
});

it('moves what came before into credits and lots, once', function () {
    $plan = SaasPlan::query()->create(['code' => 'old', 'name_ar' => 'قديمة', 'name_en' => 'Old', 'features' => ['ai'], 'ai_uses' => 100, 'video_minutes' => 600]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'Before credits', ['ai_uses' => 50]);
    $db = DB::connection('pgsql_platform');
    $db->table('usage_topups')->insert([
        ['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'limit' => 'video_minutes', 'amount' => 300, 'period' => '2026-10-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'limit' => 'ai_uses', 'amount' => 100, 'period' => '2026-10-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'limit' => 'ai_uses', 'amount' => 100, 'period' => '2026-09-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    ]);
    foreach ([['practice_items', 0], ['practice_items', 0], ['report_draft', 50], ['report_draft', 0], ['recitation_check', 130]] as [$feature, $audio]) {
        DB::table('ai_usage')->insert(['id' => (string) Str::uuid7(), 'feature' => $feature, 'provider' => 'x', 'model' => 'm', 'audio_seconds' => $audio, 'outcome' => 'ok', 'created_at' => now()]);
    }

    $migration = require base_path('modules/Platform/database/migrations/2027_08_20_100000_create_usage_ledger.php');
    $was = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_owner');
    try {
        (fn () => $this->moveIn())->call($migration);
    } finally {
        DB::setDefaultConnection($was);
    }

    expect($plan->fresh()->ai_uses)->toBe(240)
        ->and(app(AcademyPlan::class)->limit(Limit::AiUses))->toBe(120);
    app(PlatformAcademyPlan::class)->forget();
    expect(app(AcademyPlan::class)->limit(Limit::AiUses))->toBe(120);
    $ai = usageLedger()->state($this->academy->id, Meter::AiCredits);
    $video = usageLedger()->state($this->academy->id, Meter::VideoMinutes);
    // AI: 120 a month and the pack's 240 to the month's end; used 2 + (2+1) + (0+2×3) = 11.
    expect($ai->allowance)->toBe(120)->and($ai->used)->toBe(11)->and($ai->allowanceLeft)->toBe(109)->and($ai->bought)->toBe(240)
        ->and($ai->lots[0]['expires_at']->equalTo(CarbonImmutable::parse('2026-11-01', 'Africa/Cairo')))->toBeTrue()
        ->and($video->allowance)->toBe(600)->and($video->bought)->toBe(300);
});

it('shows the academy its usage page, with the cards, the charts and the statement, and the spreadsheet', function () {
    meteredPlan(['video_minutes' => 600, 'features' => ['ai'], 'ai_uses' => 240]);
    $id = $this->academy->id;
    usageLedger()->addLot($id, Meter::AiCredits, Ledger::PURCHASE, 720, CarbonImmutable::now()->addYear(), ['ref_type' => 'topup', 'ref_id' => 'p']);
    usageLedger()->consume($id, Meter::AiCredits, 30, ['subject' => 'report_draft', 'user_id' => $this->teacher->id]);
    usageLedger()->consume($id, Meter::VideoMinutes, 45, ['subject' => 'lesson', 'user_id' => $this->teacher->id]);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);

    Livewire::test(UsagePage::class)->assertOk()
        ->assertSee(__('platform::metering.page.title'))
        ->assertSee(__('shared::plans.meters.ai_credits'))->assertSee(__('shared::plans.meters.video_minutes'))
        ->assertSee(__('platform::metering.card.allowance', ['used' => '30 نقطة', 'max' => '240 نقطة']))
        ->assertSee('720 نقطة')
        ->assertSee($this->teacher->name)
        ->assertSee(__('platform::metering.subjects.lesson'))
        ->call('showMeter', 'ai_credits')->assertSee(__('platform::metering.lines.purchase'))->assertSee(__('shared::ai.features.report_draft'))
        ->callAction('halfAlert')->assertNotified(__('platform::metering.alerts.saved'))
        ->callAction('export')->assertFileDownloaded();

    // A teacher has no such page; the plan page links to it.
    $this->actingAs($this->teacher);
    expect(UsagePage::canAccess())->toBeFalse();
});

it('gives the console its rates page and an academy\'s usage with its tools', function () {
    meteredPlan(['video_minutes' => 600, 'features' => ['ai'], 'ai_uses' => 240]);
    usageLedger()->consume($this->academy->id, Meter::VideoMinutes, 45, ['subject' => 'lesson']);
    $this->clearTenant();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->platformOwner, 'platform');
    AuditActor::use(new AuditActor('platform', $this->platformOwner->id, $this->platformOwner->name));

    Livewire::test(AiCreditRatesPage::class)->assertOk()->assertSee(__('shared::ai.features.report_draft'))
        ->callAction('edit', ['base' => 2, 'per_minute' => 3], ['capability' => 'report_draft'])->assertHasNoActionErrors()
        ->assertSee(__('platform::metering.rates.own'));

    Livewire::test(AcademyOverview::class, ['academy' => $this->academy->id])->assertOk()
        ->assertSee(__('platform::metering.console.title'))
        ->callAction('credit', ['kind' => 'bonus', 'meter' => 'video_minutes', 'amount' => 100, 'months' => 12, 'reason' => 'Welcome gift'])->assertHasNoActionErrors()
        ->callAction('hardLimit', ['reason' => 'They asked for it'])->assertHasNoActionErrors();
    expect(DB::connection('pgsql_platform')->table('academy_metering')->where('academy_id', $this->academy->id)->value('video_hard_limit'))->toBeTrue();

    $this->actingAs($this->helperAdmin, 'platform');
    expect(AiCreditRatesPage::canAccess())->toBeFalse();
});
