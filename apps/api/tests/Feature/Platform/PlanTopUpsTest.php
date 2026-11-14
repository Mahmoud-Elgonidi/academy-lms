<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Classroom\Actions\MeterRoom;
use Modules\Classroom\Models\Room;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\Plans\HandlePlanUpgrade;
use Modules\Platform\Actions\Plans\RequestPlanUpgrade;
use Modules\Platform\Actions\Plans\SavePlan;
use Modules\Platform\Actions\Plans\SaveTopUpPack;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Platform\Actions\TopUps\ActivateTopUp;
use Modules\Platform\Actions\TopUps\CancelTopUpRequest;
use Modules\Platform\Actions\TopUps\DeclineTopUp;
use Modules\Platform\Actions\TopUps\RequestTopUp;
use Modules\Platform\Filament\Console\Pages\PlanRequests;
use Modules\Platform\Filament\Console\Pages\Plans;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Metering\AiCreditRates;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\PlanWarning;
use Modules\Platform\Plans\Requests\PlanRequestAnswered;
use Modules\Platform\Plans\Requests\PlanRequestMail;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Http\ProblemDetails;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The month's limits and the ways on (docs/architecture/02, "باقات
 * الأكاديميات وحدودها"): the AI helpers stop at the plan's monthly uses and
 * the work goes on by hand; video minutes are only warned about; at 80% and
 * at the limit the staff hear it with two ways on, an extra pack for this
 * month or a bigger plan, both asked from the academy and answered by the
 * platform team in the console. An extra pack is a lot in the usage ledger
 * that lasts 12 months (UsageLedgerTest has the ledger itself).
 *
 * The fixture (EnrolledStudent): academy "noor" with an admin, two teachers
 * and a student enrolled, Monday 5 October 2026.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['email' => 'owner@noor.test', 'locale' => 'en']);
    $this->platformAdmin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'k', 'platform.ai.daily_cap' => 500]);
    Mail::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** Puts noor on a new plan with these limits and areas. */
function topUpPlan(array $attributes = [], array $limits = []): SaasPlan
{
    $plan = SaasPlan::query()->create($attributes + ['code' => 'p-'.Str::lower(Str::random(6)), 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle(test()->academy->id, $plan->id, 'For this test', $limits);
    app(PlanWarning::class)->forget(test()->academy->id);

    return $plan;
}

/** One of the packs the migration starts the platform with (tests start with none). */
function topUpPack(Limit $limit, int $amount): TopUpPack
{
    $names = [300 => 'صغيرة', 600 => 'متوسطة', 1500 => 'كبيرة', 100 => 'صغيرة', 1000 => 'كبيرة'];

    return TopUpPack::query()->firstOrCreate(['limit' => $limit->value, 'amount' => $amount], ['name_ar' => $names[$amount] ?? 'رصيد', 'name_en' => 'Pack '.$amount]);
}

/** A lesson held on our own video: its room was open 30 minutes and closed, counted into the month. */
function thirtyVideoMinutes(): void
{
    $lesson = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->first();
    $room = Room::query()->create(['session_id' => $lesson->id, 'provider' => 'livekit', 'name' => 'r', 'started_at' => $lesson->starts_at, 'finished_at' => $lesson->starts_at->addMinutes(30)]);
    app(MeterRoom::class)->handle($room);
    app(PlanWarning::class)->forget(test()->academy->id);
}

/** So many requests of a helper that answered, each charged its credits (practice suggestions: one each). */
function useAiTimes(int $times, AiFeature $feature = AiFeature::PracticeItems): void
{
    foreach (range(1, $times) as $_) {
        app(Meters::class)->consume(test()->academy->id, Meter::AiCredits, app(AiCreditRates::class)->charge($feature->value), ['subject' => $feature->value]);
    }
    app(PlanWarning::class)->forget(test()->academy->id);
}

it('stops the AI helpers at the month\'s uses, and brings them back with an extra pack or the next month', function () {
    topUpPlan(['features' => ['ai'], 'ai_uses' => 3]);
    app(SaveAiSettings::class)->handle(['practice_items' => true, 'report_draft' => true], $this->owner->id);
    $ai = app(AcademyAi::class);
    app()->setLocale('ar');

    // Each request that answered is charged its credits (UsageLedgerTest has the rates and the failures).
    useAiTimes(2);
    expect(collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::AiUses)->used)->toBe(2)
        ->and($ai->on(AiFeature::PracticeItems))->toBeTrue();

    useAiTimes(1);
    expect($ai->why(AiFeature::PracticeItems))->toBe('quota')
        ->and($ai->why(AiFeature::ReportDraft))->toBe('quota');

    // The person using a helper reads why, and that the work goes on by hand; the API answers 429 ai-quota.
    $e = new AiFailed('quota');
    expect($e->sayable())->toContain('يدويًا')->toContain('رصيد')
        ->and($e->status())->toBe(429)->and($e->type())->toBe('ai-quota');
    $response = ProblemDetails::render($e, request()->create('/api/v1/teacher/reports/x/draft', 'POST', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar']));
    expect($response->getStatusCode())->toBe(429)->and($response->getData(true))->toMatchArray(['type' => 'ai-quota', 'reason' => 'quota']);

    // An extra pack is nothing until the platform team turns it on.
    $this->actingAs($this->owner);
    $topUp = app(RequestTopUp::class)->handle(topUpPack(Limit::AiUses, 100)->id, 'We pay by transfer', $this->owner->id);
    expect($ai->why(AiFeature::PracticeItems))->toBe('quota');
    app(ActivateTopUp::class)->handle($this->platformAdmin, $topUp->id);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue()
        ->and(collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::AiUses)->allowed())->toBe(103);

    // Next month: the plan's own amount comes back; the pack, used up here, would have lasted 12 months.
    useAiTimes(100);
    expect($ai->why(AiFeature::PracticeItems))->toBe('quota');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 00:30', $this->academy->defaultTimezone));
    $line = collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::AiUses);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue()->and($line->used)->toBe(0)->and($line->extra)->toBe(0)->and($line->allowed())->toBe(3);

    // No limit on the plan: never stopped.
    topUpPlan(['features' => ['ai']]);
    useAiTimes(5);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue();
});

it('raises the month\'s video minutes with an extra pack, and the warning clears', function () {
    topUpPlan(['video_minutes' => 30]);
    thirtyVideoMinutes();
    $this->actingAs($this->owner);
    app()->setLocale('ar');

    [$warning] = app(PlanWarning::class)->forViewer();
    $labels = array_column($warning['actions'], 'label');
    expect($warning['limit'])->toBe('video_minutes')->and($warning['state'])->toBe('full')
        ->and($labels)->toBe([__('platform::plans.banner.buy_video'), __('platform::plans.banner.upgrade'), __('platform::plans.banner.video_action')])
        ->and($warning['actions'][0]['url'])->toEndWith('/admin/settings/plan?buy=video_minutes')
        ->and($warning['actions'][1]['url'])->toEndWith('/admin/settings/plan?upgrade=1');

    // Asked: the button says it waits for the platform team.
    $topUp = app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 300)->id, null, $this->owner->id);
    [$warning] = app(PlanWarning::class)->forViewer();
    expect($warning['actions'][0])->toBe(['label' => __('platform::plans.banner.buy_waiting'), 'url' => null]);

    app(ActivateTopUp::class)->handle($this->platformAdmin, $topUp->id);
    $line = collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::VideoMinutes);
    expect($line->max)->toBe(30)->and($line->extra)->toBe(300)->and($line->allowed())->toBe(330)->and($line->state())->toBe('ok')
        ->and(app(PlanWarning::class)->forViewer())->toBe([]);

    // The night's count keeps what is allowed now.
    $this->clearTenant();
    $this->artisan('platform:usage')->assertSuccessful();
    expect((int) DB::connection('pgsql_platform')->table('usage_counters')->where('academy_id', $this->academy->id)->where('metric', 'video_minutes')->value('max'))->toBe(330);
});

it('shows both monthly warnings at once, each with its ways on', function () {
    topUpPlan(['video_minutes' => 36, 'features' => ['ai'], 'ai_uses' => 10]);
    thirtyVideoMinutes();
    useAiTimes(10);
    $this->actingAs($this->owner);
    app()->setLocale('ar');

    $warnings = collect(app(PlanWarning::class)->forViewer())->keyBy('limit');
    expect($warnings->keys()->all())->toBe(['ai_uses', 'video_minutes'])
        ->and($warnings['ai_uses']['state'])->toBe('full')->and($warnings['video_minutes']['state'])->toBe('near')
        ->and($warnings['ai_uses']['text'])->toContain('يدويًا')->toContain(PlanFormat::usage(Limit::AiUses, 10, 10))
        ->and($warnings['video_minutes']['text'])->toContain('Zoom')
        ->and(array_column($warnings['ai_uses']['actions'], 'label'))->toBe([__('platform::plans.banner.buy_ai'), __('platform::plans.banner.upgrade')]);

    $html = view('platform::plans.banner', ['warnings' => $warnings->values()->all()])->render();
    expect($html)->toContain(__('platform::plans.banner.buy_ai'))->toContain(__('platform::plans.banner.buy_video'))
        ->toContain(__('platform::plans.banner.upgrade'))->toContain(__('platform::plans.banner.video_action'))
        ->toContain('data-state="full"')->toContain('data-state="near"');

    // An upgrade waiting is said in place of its button; a teacher hears nothing.
    app(RequestPlanUpgrade::class)->handle(null, 'More video please', $this->owner->id);
    expect(collect(app(PlanWarning::class)->forViewer()[0]['actions'])->pluck('label'))->toContain(__('platform::plans.banner.upgrade_waiting'));
    $this->actingAs($this->teacher);
    expect(app(PlanWarning::class)->forViewer())->toBe([]);
});

it('lets only the owner and admins ask, never while signed in as someone, one waiting per kind', function () {
    topUpPlan(['video_minutes' => 600, 'features' => ['ai'], 'ai_uses' => 100]);
    $video = topUpPack(Limit::VideoMinutes, 600);

    $this->actingAs($this->teacher);
    expect(fn () => app(RequestTopUp::class)->handle($video->id, null, $this->teacher->id))->toThrow(AuthorizationException::class);

    $this->actingAs($this->owner);
    AuditActor::use(new AuditActor('platform', $this->platformAdmin->id, 'سلمى', onBehalfOf: $this->owner->id));
    expect(fn () => app(RequestTopUp::class)->handle($video->id, null, $this->owner->id))->toThrow(ValidationException::class)
        ->and(fn () => app(RequestPlanUpgrade::class)->handle(null, 'More', $this->owner->id))->toThrow(ValidationException::class);
    AuditActor::forget();

    $first = app(RequestTopUp::class)->handle($video->id, null, $this->owner->id);
    expect($first->status)->toBe('requested')->and($first->amount)->toBe(600)->and($first->period)->toBe('2026-10-01')
        ->and(fn () => app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 300)->id, null, $this->owner->id))->toThrow(ValidationException::class);
    app(RequestTopUp::class)->handle(topUpPack(Limit::AiUses, 100)->id, null, $this->owner->id);   // another kind may wait beside it
    Mail::assertQueued(PlanRequestMail::class, 2);

    // Cancelled while it waits, then asked again; an answered one stays as it was.
    app(CancelTopUpRequest::class)->handle($first->id);
    expect($first->fresh()->status)->toBe('cancelled')
        ->and(fn () => app(CancelTopUpRequest::class)->handle($first->id))->toThrow(ValidationException::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('usage_topups')->where('id', $first->id)->update(['status' => 'active']))->toThrow(QueryException::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('usage_topups')->where('id', $first->id)->delete())->toThrow(QueryException::class);
    app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 300)->id, null, $this->owner->id);

    // An archived pack, or one for a limit the plan does not set, is not asked for.
    app(SaveTopUpPack::class)->handle($video->id, ['limit' => 'video_minutes', 'amount' => 600, 'name_ar' => 'متوسطة', 'name_en' => 'Medium', 'archived' => true]);
    UsageTopUp::query()->where('status', 'requested')->get()->each(fn ($t) => app(CancelTopUpRequest::class)->handle($t->id));
    expect(fn () => app(RequestTopUp::class)->handle($video->id, null, $this->owner->id))->toThrow(ValidationException::class);
    topUpPlan(['features' => []]);
    expect(fn () => app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 300)->id, null, $this->owner->id))->toThrow(ValidationException::class)
        ->and(fn () => app(RequestTopUp::class)->handle(topUpPack(Limit::AiUses, 100)->id, null, $this->owner->id))->toThrow(ValidationException::class);

    // Each step is in the academy's log.
    $actions = DB::connection('pgsql_platform')->table('audit_logs')->where('academy_id', $this->academy->id)->where('action', 'like', 'academy.topup_%')->pluck('action')->all();
    expect(array_count_values($actions))->toMatchArray(['academy.topup_requested' => 3, 'academy.topup_cancelled' => 3]);
});

it('lets the platform team turn a pack on or down in the console, audited, and tells whoever asked', function () {
    topUpPlan(['video_minutes' => 600, 'features' => ['ai'], 'ai_uses' => 100]);
    $this->actingAs($this->owner);
    $video = app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 1500)->id, 'Bank transfer', $this->owner->id);
    $ai = app(RequestTopUp::class)->handle(topUpPack(Limit::AiUses, 300)->id, null, $this->owner->id);
    $upgrade = app(RequestPlanUpgrade::class)->handle(null, 'We grow', $this->owner->id);
    expect(fn () => app(RequestPlanUpgrade::class)->handle(null, 'Again', $this->owner->id))->toThrow(ValidationException::class);

    // A locked-out admin cannot answer.
    $gone = PlatformAdmin::query()->create(['name' => 'Gone', 'email' => 'gone@platform.test', 'password' => 'a-long-password', 'is_active' => false]);
    expect(fn () => app(ActivateTopUp::class)->handle($gone, $video->id))->toThrow(AuthorizationException::class);

    $this->clearTenant();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->platformAdmin, 'platform');
    AuditActor::use(new AuditActor('platform', $this->platformAdmin->id, $this->platformAdmin->name));
    app()->setLocale('ar');

    Livewire::test(PlanRequests::class)->assertOk()->assertSee('Bank transfer')->assertSee('We grow')
        ->assertSee(__('platform::topups.console.kinds.topup'))->assertSee(__('platform::topups.console.kinds.upgrade'))
        ->callAction('activate', [], ['topup' => $video->id])->assertHasNoActionErrors()
        ->callAction('decline', ['reason' => ''], ['topup' => $ai->id])->assertHasActionErrors(['reason']);
    Livewire::test(PlanRequests::class)
        ->callAction('decline', ['reason' => 'No payment arrived'], ['topup' => $ai->id])->assertHasNoActionErrors()
        ->callAction('handled', [], ['request' => $upgrade->id])->assertHasNoActionErrors()
        ->assertSee(__('platform::topups.console.empty'));

    expect($video->fresh()->status)->toBe('active')->and($video->fresh()->decided_by)->toBe($this->platformAdmin->id)
        ->and($ai->fresh()->status)->toBe('declined')->and($ai->fresh()->reason)->toBe('No payment arrived')
        ->and($upgrade->fresh()->status)->toBe('handled')
        ->and(fn () => app(DeclineTopUp::class)->handle($this->platformAdmin, $video->id, 'Too late now'))->toThrow(ValidationException::class)
        ->and(fn () => app(HandlePlanUpgrade::class)->handle($this->platformAdmin, $upgrade->id))->toThrow(ValidationException::class);

    $log = DB::connection('pgsql_platform')->table('audit_logs')->whereIn('action', ['academy.topup_activated', 'academy.topup_declined', 'academy.upgrade_handled'])->get();
    expect($log->pluck('action')->sort()->values()->all())->toBe(['academy.topup_activated', 'academy.topup_declined', 'academy.upgrade_handled'])
        ->and($log->pluck('actor_type')->unique()->all())->toBe(['platform'])
        ->and($log->pluck('academy_id')->unique()->all())->toBe([$this->academy->id])
        ->and($log->firstWhere('action', 'academy.topup_declined')->data)->not->toContain('No payment');

    Mail::assertQueued(PlanRequestAnswered::class, fn (PlanRequestAnswered $m) => $m->hasTo('owner@noor.test') && $m->answer === 'active' && str_contains($m->words['extra'], '1,500'));
    Mail::assertQueued(PlanRequestAnswered::class, fn (PlanRequestAnswered $m) => $m->answer === 'declined' && $m->words['reason'] === 'No payment arrived');

    // The academy sees what the month holds now.
    $this->actAsTenant($this->academy);
    $line = collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::VideoMinutes);
    expect($line->allowed())->toBe(2100);
});

it('turns a pack on for the academy\'s current month, even when it was asked for the month before', function () {
    topUpPlan(['video_minutes' => 600]);
    $this->actingAs($this->owner);
    $topUp = app(RequestTopUp::class)->handle(topUpPack(Limit::VideoMinutes, 300)->id, null, $this->owner->id);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', $this->academy->defaultTimezone));
    app(ActivateTopUp::class)->handle($this->platformAdmin, $topUp->id);
    expect($topUp->fresh()->period)->toBe('2026-11-01')
        ->and(collect(app(PlanUsage::class)->lines())->firstWhere('limit', Limit::VideoMinutes)->extra)->toBe(300);
});

it('shows the academy what is used, left and extra, and asks from the plan page', function () {
    topUpPlan(['video_minutes' => 100, 'features' => ['ai'], 'ai_uses' => 50]);
    thirtyVideoMinutes();
    useAiTimes(5);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    app()->setLocale('ar');
    $p = topUpPack(Limit::VideoMinutes, 300);
    $renews = CarbonImmutable::now()->setTimezone($this->academy->defaultTimezone)->startOfMonth()->addMonth()->locale('ar')->translatedFormat('j F');

    Livewire::test(AcademyPlanPage::class)->assertOk()
        ->assertSee(__('shared::plans.limits.ai_uses'))
        ->assertSee(__('platform::plans.of', ['used' => 30, 'max' => 100]))
        ->assertSee(__('platform::plans.of', ['used' => 5, 'max' => 50]))
        ->assertSee(__('platform::plans.summary.left', ['left' => PlanFormat::amount(Limit::AiUses, 45)]))
        ->assertSee(__('platform::plans.summary.renews', ['date' => $renews]))
        ->assertSee(__('platform::topups.list.empty'))
        ->callAction('buy', ['pack' => $p->id, 'note' => 'Transfer'], ['limit' => 'video_minutes'])->assertHasNoActionErrors()
        ->assertSee(__('platform::topups.status.requested'))->assertSee(__('platform::topups.buy.waiting'))
        ->callAction('upgrade', ['plan' => null, 'note' => 'More teachers'])->assertHasNoActionErrors()
        ->assertSee(__('platform::topups.list.upgrade'));

    $topUp = UsageTopUp::query()->sole();
    expect($topUp->pack_id)->toBe($p->id)->and($topUp->note)->toBe('Transfer')->and($topUp->requested_by)->toBe($this->owner->id)
        ->and(PlanUpgradeRequest::query()->sole()->note)->toBe('More teachers');

    // Turned on: the meter says the plan's amount and the month's extra apart.
    app(ActivateTopUp::class)->handle($this->platformAdmin, $topUp->id);
    Livewire::test(AcademyPlanPage::class)
        ->assertSee(__('platform::plans.of', ['used' => 30, 'max' => 400]))
        ->assertSee(__('platform::plans.summary.extra', ['plan' => PlanFormat::amount(Limit::VideoMinutes, 100), 'extra' => PlanFormat::amount(Limit::VideoMinutes, 300)]))
        ->assertSee(__('platform::topups.status.active'));

    // The warning's button opens the window in place; cancelling a waiting one from the list.
    $ai = app(RequestTopUp::class)->handle(topUpPack(Limit::AiUses, 100)->id, null, $this->owner->id);
    Livewire::withQueryParams(['buy' => 'video_minutes'])->test(AcademyPlanPage::class)->assertSet('defaultAction', 'buy')->assertSet('defaultActionArguments', ['limit' => 'video_minutes'])
        ->assertSeeHtml('wire:init="mountAction(');
    Livewire::withQueryParams(['upgrade' => '1'])->test(AcademyPlanPage::class)->assertSet('defaultAction', 'upgrade');
    Livewire::test(AcademyPlanPage::class)->callAction('cancelTopUp', [], ['topup' => $ai->id])->assertHasNoActionErrors();
    expect($ai->fresh()->status)->toBe('cancelled');
});

it('sets AI uses per plan and per academy, and the extra packs, from the console', function () {
    $saved = app(SavePlan::class)->handle(null, ['code' => 'aa', 'name_ar' => 'أ', 'name_en' => 'A', 'ai_uses' => 250, 'features' => ['ai']]);
    expect($saved->ai_uses)->toBe(250)
        ->and(fn () => app(SavePlan::class)->handle($saved->id, ['code' => 'aa', 'name_ar' => 'أ', 'name_en' => 'A', 'ai_uses' => -1]))->toThrow(ValidationException::class);

    app(ChangeAcademyPlan::class)->handle($this->academy->id, $saved->id, 'Own AI uses', ['ai_uses' => 40]);
    expect(app(AcademyPlan::class)->limit(Limit::AiUses))->toBe(40);

    $this->clearTenant();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->platformAdmin, 'platform');
    AuditActor::use(new AuditActor('platform', $this->platformAdmin->id, $this->platformAdmin->name));
    app()->setLocale('ar');

    topUpPack(Limit::AiUses, 1000);
    Livewire::test(Plans::class)->assertSee(__('platform::topups.packs.title'))->assertSee('+'.PlanFormat::amount(Limit::AiUses, 1000))
        ->callAction('new', ['code' => 'bb', 'name_ar' => 'ب', 'name_en' => 'B', 'ai_uses' => '75', 'features' => ['ai']])->assertHasNoActionErrors()
        ->callAction('newPack', ['limit' => 'ai_uses', 'amount' => '2000', 'name_ar' => 'ضخمة', 'name_en' => 'Huge', 'prices' => [['currency' => 'SAR', 'price' => '99'], ['currency' => 'USD', 'price' => '26.5']]], ['limit' => 'ai_uses'])->assertHasNoActionErrors()
        ->assertSee('ضخمة');
    expect(SaasPlan::query()->where('code', 'bb')->sole()->ai_uses)->toBe(75);
    $huge = TopUpPack::query()->where('name_en', 'Huge')->sole();
    expect($huge->amount)->toBe(2000)->and($huge->prices)->toBe(['SAR' => 9900, 'USD' => 2650]);

    Livewire::test(Plans::class)
        ->callAction('editPack', ['limit' => 'ai_uses', 'amount' => '2500', 'name_ar' => 'ضخمة', 'name_en' => 'Huge', 'archived' => true], ['pack' => $huge->id])->assertHasNoActionErrors();
    expect($huge->fresh()->amount)->toBe(2500)->and($huge->fresh()->archived_at)->not->toBeNull()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.topup_pack_saved')->count())->toBe(2)
        ->and(fn () => app(SaveTopUpPack::class)->handle(null, ['limit' => 'teachers', 'amount' => 5, 'name_ar' => 'x', 'name_en' => 'x']))->toThrow(ValidationException::class);

});
