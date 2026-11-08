<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Ai\CompareAiChains;
use Modules\Platform\Actions\Ai\ImportAiEngines;
use Modules\Platform\Actions\Ai\SaveAiEngine;
use Modules\Platform\Actions\Ai\SaveAiRoutes;
use Modules\Platform\Actions\Ai\SaveAiTestCase;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Platform\Ai\Engines\EngineProbe;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\Gateway\Routes;
use Modules\Platform\Ai\Gateway\Split;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Filament\Console\Pages\AiEnginesPage;
use Modules\Platform\Filament\Console\Pages\AiRoutesPage;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Logging\Redactor;

/*
 * The AI gateway (docs/architecture/02, section 22): every model call goes
 * through one place. Modules register their capabilities and ask the
 * contracts with a key; the console's owners set the engines and, for each
 * capability, the chain of engines (default, a plan's, an academy's) with an
 * optional share to a second chain. The gateway falls back on an engine's
 * trouble, never on an answer it cannot use, and notes each try.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'en']);
    $this->platformOwner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->platformOwner->forceFill(['is_owner' => true])->save();
    $this->helper = PlatformAdmin::query()->create(['name' => 'Omar', 'email' => 'omar@platform.test', 'password' => 'a-long-password']);
    config([
        'platform.ai.driver' => 'claude',
        'platform.ai.key' => 'a-claude-key-in-settings',
        'platform.ai.speech.key' => 'a-speech-key',
        'platform.ai.daily_cap' => 500,
        'platform.ai.openai' => ['key' => 'sk-settings', 'base_url' => 'https://api.openai.com/v1', 'models' => ['best' => 'gpt-settings', 'fast' => '']],
    ]);
    app()->setLocale('en');
    app(SaveAiSettings::class)->handle(['practice_items' => true, 'report_draft' => true, 'digest_wording' => true], $this->owner->id);
});

afterEach(function () {
    app(Capabilities::class)->forget('lesson_summary');
    AuditActor::forget();
    Context::forget(Routes::REQUEST);
});

/** An OpenAI-compatible engine (a server of one's own, say) at this address. */
function gatewayEngine(string $name, string $host, array $more = []): AiEngine
{
    return app(SaveAiEngine::class)->handle(test()->platformOwner, null, $more + [
        'name' => $name, 'kind' => 'openai_compatible', 'base_url' => "https://{$host}/v1", 'api_key' => 'sk-'.Str::slug($name).'-secret-value',
        'model_best' => 'model-'.Str::slug($name), 'enabled' => true,
    ]);
}

/** Its chat answer, as an OpenAI-compatible server sends it. */
function gatewayAnswer(array $data, string $finish = 'stop'): array
{
    return ['model' => 'served-model', 'choices' => [['finish_reason' => $finish, 'message' => ['role' => 'assistant', 'content' => json_encode($data)]]],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30]];
}

function gatewayPrompt(): AiPrompt
{
    return new AiPrompt('Answer as asked.', 'Two items.', ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['items'], 'additionalProperties' => false]);
}

function gatewayRoute(string $capability, string $scope, array $engines, string $kind = 'text', ?int $split = null, array $second = []): void
{
    $chain = fn (array $list) => array_map(fn ($e) => is_array($e) ? ['engine' => $e[0]->id, 'tier' => $e[1]] : ['engine' => $e->id, 'tier' => null], $list);
    app(SaveAiRoutes::class)->handle(test()->platformOwner, $capability, $kind, $scope, $chain($engines), $split, $chain($second));
}

function gatewayReason(callable $call): string
{
    try {
        $call();
    } catch (AiFailed $e) {
        return $e->reason;
    }

    return 'none';
}

it('lets a module register a capability that appears in the console, is on for academies, and routes through the gateway', function () {
    app(Capabilities::class)->add(new Capability('lesson_summary', ['text'], tier: 'fast', credits: 3, label: 'A summary of the lesson', module: 'tests'));
    $own = gatewayEngine('Own GPU', 'gpu.example.com');
    gatewayRoute('lesson_summary', AiRoute::DEFAULT, [$own]);
    Http::fake(['gpu.example.com/v1/chat/completions' => Http::response(gatewayAnswer(['items' => ['a', 'b']]))]);

    // On in the academy at once (only the first five start off), and switchable there.
    expect(app(AcademyAi::class)->on('lesson_summary'))->toBeTrue();
    $answer = app(LanguageModel::class)->json('lesson_summary', gatewayPrompt());
    expect($answer->data)->toBe(['items' => ['a', 'b']]);
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-own-gpu-secret-value') && $r['model'] === 'model-own-gpu');
    $row = DB::table('ai_usage')->sole();
    expect($row->feature)->toBe('lesson_summary')->and($row->engine_id)->toBe($own->id)->and($row->provider)->toBe('openai_compatible')
        ->and($row->latency_ms)->toBeInt()->and($row->fallback_from)->toBeNull();

    app(SaveAiSettings::class)->handle(['practice_items' => true, 'lesson_summary' => false], $this->owner->id);
    expect(AiSettings::current()->disabled)->toBe(['lesson_summary'])
        ->and(app(AcademyAi::class)->why('lesson_summary'))->toBe('off')
        ->and(app(AcademyAi::class)->on(AiFeature::PracticeItems))->toBeTrue()
        ->and(gatewayReason(fn () => app(LanguageModel::class)->json('lesson_summary', gatewayPrompt())))->toBe('off');

    // A key no module registered is refused.
    expect(fn () => app(LanguageModel::class)->json('nobody_registered_this', gatewayPrompt()))->toThrow(InvalidArgumentException::class);

    // The console's routes page has a row for it, with its route.
    $this->actingAs($this->platformOwner, 'platform');
    Livewire::test(AiRoutesPage::class)->assertOk()->assertSee('A summary of the lesson')->assertSee('lesson_summary')->assertSee('Own GPU');
});

it('takes the academy\'s own route, then its plan\'s, then the default, then the server\'s settings', function () {
    $default = gatewayEngine('Default engine', 'default.example.com');
    $planned = gatewayEngine('Plan engine', 'plan.example.com');
    $mine = gatewayEngine('Academy engine', 'academy.example.com');
    $plan = SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'احترافية', 'name_en' => 'Pro', 'features' => ['ai']]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    app(PlatformAcademyPlan::class)->forget();
    $routes = app(Routes::class);
    $first = fn () => $routes->resolve('practice_items', 'text');

    // No route: the server's settings, as before engines.
    expect($first()->scope)->toBe('settings')->and($first()->steps[0]->engine->id)->toBeNull()
        ->and($first()->steps[0]->engine->key)->toBe('a-claude-key-in-settings');

    gatewayRoute('practice_items', AiRoute::DEFAULT, [$default]);
    expect($first()->scope)->toBe('default')->and($first()->steps[0]->engine->id)->toBe($default->id);
    gatewayRoute('practice_items', 'plan:pro', [[$planned, 'fast']]);
    expect($first()->scope)->toBe('plan:pro')->and($first()->steps[0]->engine->id)->toBe($planned->id)->and($first()->steps[0]->tier)->toBe('fast');
    gatewayRoute('practice_items', 'academy:'.$this->academy->id, [$mine, $default]);
    expect($first()->scope)->toBe('academy:'.$this->academy->id)->and(array_map(fn ($s) => $s->engine->id, $first()->steps))->toBe([$mine->id, $default->id]);

    // An engine switched off leaves the chain; a route with none left gives way to the next scope.
    app(SaveAiEngine::class)->handle($this->platformOwner, $mine->id, ['enabled' => false] + $mine->only(['name', 'kind', 'base_url', 'model_best']));
    expect(array_map(fn ($s) => $s->engine->id, $first()->steps))->toBe([$default->id]);
    gatewayRoute('practice_items', 'academy:'.$this->academy->id, [$mine]);
    expect($first()->scope)->toBe('plan:pro');

    // Another academy on no plan: the default.
    $this->actAsTenant($this->createAcademy(['slug' => 'rawda']));
    app(PlatformAcademyPlan::class)->forget();
    expect(app(Routes::class)->resolve('practice_items', 'text')->scope)->toBe('default');

    // Removing the default gives the server's settings back.
    app(SaveAiRoutes::class)->handle($this->platformOwner, 'practice_items', 'text', AiRoute::DEFAULT, []);
    expect(app(Routes::class)->resolve('practice_items', 'text')->scope)->toBe('settings');
});

it('falls back to the next engine on an error or a time-out, and notes which engine it fell back from', function () {
    $down = gatewayEngine('Down engine', 'down.example.com');
    $slow = gatewayEngine('Slow engine', 'slow.example.com');
    $good = gatewayEngine('Good engine', 'good.example.com');
    gatewayRoute('practice_items', AiRoute::DEFAULT, [$down, $slow, $good]);
    Http::fake([
        'down.example.com/*' => Http::response(['error' => ['type' => 'server_error']], 503),
        'slow.example.com/*' => fn () => throw new ConnectionException('Operation timed out'),
        'good.example.com/*' => Http::response(gatewayAnswer(['items' => ['ok']])),
    ]);

    expect(app(LanguageModel::class)->json(AiFeature::PracticeItems, gatewayPrompt())->data)->toBe(['items' => ['ok']]);
    $rows = DB::table('ai_usage')->orderBy('created_at')->get();
    expect($rows->pluck('engine_id')->all())->toEqualCanonicalizing([$down->id, $slow->id, $good->id])
        ->and($rows->firstWhere('engine_id', $good->id)->fallback_from)->toBe($slow->id)
        ->and($rows->firstWhere('engine_id', $slow->id)->fallback_from)->toBe($down->id)
        ->and($rows->firstWhere('engine_id', $down->id)->fallback_from)->toBeNull()
        ->and($rows->firstWhere('engine_id', $good->id)->outcome)->toBe('ok')
        ->and($rows->where('outcome', 'unavailable')->count())->toBe(2)
        // Each engine's own health, on the health page.
        ->and(ServiceHealth::problem($down->service()))->not->toBeNull()
        ->and(ServiceHealth::problem($good->service()))->toBeNull();

    // All of them down: unavailable, in our words.
    gatewayRoute('practice_items', AiRoute::DEFAULT, [$down, $slow]);
    expect(gatewayReason(fn () => app(LanguageModel::class)->json(AiFeature::PracticeItems, gatewayPrompt())))->toBe('unavailable');
});

it('never falls back on a refusal or an answer it cannot use', function () {
    $first = gatewayEngine('First engine', 'first.example.com');
    $second = gatewayEngine('Second engine', 'second.example.com');
    gatewayRoute('practice_items', AiRoute::DEFAULT, [$first, $second]);
    Http::fake([
        'first.example.com/*' => Http::sequence()
            ->push(gatewayAnswer(['items' => ['cut']], 'length'))
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'not json at all']]]])
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'no', 'content' => null]]]]),
        'second.example.com/*' => Http::response(gatewayAnswer(['items' => ['x']])),
    ]);

    foreach (['too_long', 'unreadable', 'refused'] as $reason) {
        expect(gatewayReason(fn () => app(LanguageModel::class)->json(AiFeature::PracticeItems, gatewayPrompt())))->toBe($reason);
    }
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'second.example.com'));
});

it('sends a share of requests to the second chain, the same way for the same request', function () {
    // Pure: the same id and key always take the same side, and the share holds over many.
    $ids = array_map(fn ($i) => "request-{$i}", range(1, 2000));
    $second = count(array_filter($ids, fn ($id) => Split::takesSecond($id, 'practice_items', 10)));
    expect($second)->toBeGreaterThan(140)->toBeLessThan(260)
        ->and(Split::takesSecond('request-7', 'practice_items', 10))->toBe(Split::takesSecond('request-7', 'practice_items', 10))
        ->and(Split::takesSecond('x', 'practice_items', 0))->toBeFalse()
        ->and(Split::takesSecond('x', 'practice_items', 100))->toBeTrue();

    $main = gatewayEngine('Main engine', 'main.example.com');
    $trial = gatewayEngine('Trial engine', 'trial.example.com');
    gatewayRoute('practice_items', AiRoute::DEFAULT, [$main], split: 30, second: [$trial]);
    $routes = app(Routes::class);
    $sides = [];
    foreach (array_slice($ids, 0, 200) as $id) {
        $route = $routes->resolve('practice_items', 'text', $id);
        expect($route->split)->toBe(Split::takesSecond($id, 'practice_items', 30))
            ->and($route->steps[0]->engine->id)->toBe($route->split ? $trial->id : $main->id)
            ->and($routes->resolve('practice_items', 'text', $id)->split)->toBe($route->split);
        $sides[] = $route->split;
    }
    expect(array_unique($sides))->toHaveCount(2);

    // Within one request (Laravel's Context), the side never changes.
    Context::add(Routes::REQUEST, 'one-request');
    expect($routes->resolve('practice_items', 'text')->split)->toBe(Split::takesSecond('one-request', 'practice_items', 30));
});

it('keeps an engine\'s key encrypted, never shows or logs it, keeps it when left blank, and lets only owners change engines', function () {
    $engine = gatewayEngine('Secret engine', 'secret.example.com');
    $raw = DB::connection('pgsql_platform')->table('ai_engines')->where('id', $engine->id)->value('api_key');
    expect($raw)->not->toContain('secret-value')
        ->and($engine->toArray())->not->toHaveKey('api_key')
        ->and(json_encode($engine))->not->toContain('secret-value')
        ->and(Redactor::text('saving {"api_key":"sk-secret-engine-secret-value","name":"x"}'))->not->toContain('secret-value');

    // Blank keeps it; "remove" takes it away.
    $kept = app(SaveAiEngine::class)->handle($this->platformOwner, $engine->id, ['api_key' => '', 'model_fast' => 'small'] + $engine->only(['name', 'kind', 'base_url', 'model_best']));
    expect($kept->fresh()->api_key)->toBe('sk-secret-engine-secret-value');
    app(SaveAiEngine::class)->handle($this->platformOwner, $engine->id, $engine->only(['name', 'kind', 'base_url', 'model_best']), clearKey: true);
    expect($engine->fresh()->api_key)->toBeNull();

    // Audited with names and fields, never a value.
    $audit = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.ai_engine_saved')->get();
    expect($audit)->toHaveCount(3)->and($audit->pluck('data')->implode(' '))->not->toContain('secret-value')
        ->and(json_decode($audit->last()->data, true)['fields'])->toContain('api_key');

    // Owners only: the actions refuse, the screens are closed.
    expect(fn () => app(SaveAiEngine::class)->handle($this->helper, null, ['name' => 'x', 'kind' => 'demo']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveAiRoutes::class)->handle($this->helper, 'practice_items', 'text', 'default', []))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ImportAiEngines::class)->handle($this->helper))->toThrow(AuthorizationException::class);
    $this->actingAs($this->helper, 'platform');
    Livewire::test(AiEnginesPage::class)->assertForbidden();
    Livewire::test(AiRoutesPage::class)->assertForbidden();

    // The owner's screen shows the engine and that a key is kept, never the key.
    app(SaveAiEngine::class)->handle($this->platformOwner, $engine->id, ['api_key' => 'sk-new-secret-value'] + $engine->only(['name', 'kind', 'base_url', 'model_best']));
    $this->actingAs($this->platformOwner, 'platform');
    Livewire::test(AiEnginesPage::class)->assertOk()->assertSee('Secret engine')->assertSee(__('platform::engines.card.key_kept'))->assertDontSee('sk-new-secret-value')
        ->mountAction('edit', ['engine' => $engine->id])->assertDontSee('sk-new-secret-value');

    // Over the internet only over https, and never a password in the address.
    expect(fn () => gatewayEngine('Clear engine', 'x', ['base_url' => 'http://203.0.113.9/v1']))->toThrow(ValidationException::class)
        ->and(fn () => gatewayEngine('Pass engine', 'x', ['base_url' => 'https://me:pw@gpu.example.com/v1']))->toThrow(ValidationException::class)
        ->and(gatewayEngine('Private engine', 'x', ['base_url' => 'http://vllm:8000/v1'])->base_url)->toBe('http://vllm:8000/v1');
});

it('tries an engine with Http::fake: a saved one is written to its health check, a typed one nowhere', function () {
    $engine = gatewayEngine('Probe engine', 'probe.example.com', ['model_fast' => 'probe-small']);
    Http::fake([
        'probe.example.com/v1/chat/completions' => Http::sequence()->push(gatewayAnswer(['ok' => true]))->push(['error' => ['code' => 'model_not_found']], 404)
            ->push(['error' => ['type' => 'invalid_request_error']], 401),
    ]);
    $lines = app(EngineProbe::class)->try(EngineSpec::of($engine), record: true);
    expect(array_column($lines, 'level'))->toBe(['ok', 'fail'])
        ->and(implode(' ', array_column($lines, 'text')))->toContain('probe-small')->not->toContain('secret-value')
        ->and(ServiceHealth::problem($engine->service())?->value)->toBe('model');
    $check = app(SystemHealth::class)->serviceChecks();
    expect($check)->toHaveKey('service_'.$engine->service())
        ->and(SystemHealth::label('service_'.$engine->service()))->toContain('Probe engine');

    // What is typed and not saved is tried, never written to the health page.
    ServiceHealth::forget($engine->service());
    $typed = new EngineSpec(null, 'Typed', $engine->kindOf(), 'https://probe.example.com/v1', 'sk-typed', 'model-x');
    expect(app(EngineProbe::class)->try($typed)[0]['level'])->toBe('fail')
        ->and(ServiceHealth::last($engine->service()))->toBe([]);

    // A Whisper-compatible server: its list of models, with the key.
    $ears = app(SaveAiEngine::class)->handle($this->platformOwner, null, ['name' => 'Own ears', 'kind' => 'whisper_compatible', 'base_url' => 'https://speech.example.com/v1', 'model_speech' => 'large-v3', 'enabled' => true]);
    Http::fake(['speech.example.com/v1/models' => Http::response(['data' => [['id' => 'large-v3']]])]);
    expect(app(EngineProbe::class)->try(EngineSpec::of($ears), record: true)[0]['level'])->toBe('ok');
});

it('hears through a Whisper-compatible engine of the platform\'s own, told only the language', function () {
    $ears = app(SaveAiEngine::class)->handle($this->platformOwner, null, ['name' => 'Own ears', 'kind' => 'whisper_compatible', 'base_url' => 'https://speech.example.com/v1', 'model_speech' => 'large-v3', 'enabled' => true]);
    gatewayRoute('report_draft', AiRoute::DEFAULT, [$ears], kind: 'speech');
    Http::fake(['speech.example.com/v1/audio/transcriptions' => Http::response(['text' => 'قرأ جيدا', 'duration' => 3.2, 'words' => [['word' => 'قرأ', 'start' => 0.1, 'end' => 0.5], ['word' => 'جيدا', 'start' => 0.6, 'end' => 1.1]]])]);
    $path = tempnam(sys_get_temp_dir(), 'clip');
    file_put_contents($path, 'RIFF....WAVEfmt ');

    $heard = app(SpeechToText::class)->transcribe(AiFeature::ReportDraft, new AudioClip($path, 'audio/wav', 'clip.wav'), 'ara');
    expect($heard->text)->toBe('قرأ جيدا')->and($heard->words)->toHaveCount(2)->and($heard->seconds)->toBe(3.2);
    Http::assertSent(function (Request $r) {
        $fields = collect($r->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

        return str_ends_with($r->url(), '/v1/audio/transcriptions') && $fields['model'] === 'large-v3' && $fields['language'] === 'ar'
            && $fields->has('file') && ! $fields->keys()->intersect(['prompt', 'text', 'expected'])->count();
    });
    expect(DB::table('ai_usage')->sole()->engine_id)->toBe($ears->id);
});

it('compares two chains on saved test inputs, outside any academy, saving nothing for academies', function () {
    $a = gatewayEngine('Engine A', 'a.example.com', ['cost_input_per_m' => 2, 'cost_output_per_m' => 10]);
    $b = gatewayEngine('Engine B', 'b.example.com', ['cost_gpu_per_min' => 0.5]);
    $case = app(SaveAiTestCase::class)->handle($this->platformOwner, null, ['capability' => 'practice_items', 'kind' => 'text', 'title' => 'Fractions', 'prompt' => 'Two items on halves.']);
    Http::fake([
        'a.example.com/*' => Http::response(gatewayAnswer(['answer' => 'from a'])),
        'b.example.com/*' => Http::response(['error' => ['type' => 'server_error']], 500),
    ]);
    $this->clearTenant();
    $before = DB::connection('pgsql_platform')->table('ai_usage')->count();

    $rows = app(CompareAiChains::class)->handle($this->platformOwner, 'practice_items', 'text', [['engine' => $a->id]], [['engine' => $b->id]], [$case->id]);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['case'])->toBe('Fractions')
        ->and($rows[0]['a']['text'])->toContain('from a')->and($rows[0]['a']['engine'])->toBe('Engine A')
        ->and($rows[0]['a']['cost'])->toContain('USD')
        ->and($rows[0]['b']['failed'])->toBe(__('shared::ai.failed.unavailable'))
        ->and(DB::connection('pgsql_platform')->table('ai_usage')->count())->toBe($before)
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.ai_comparison_run')->count())->toBe(1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'a.example.com') && $r['messages'][1]['content'] === 'Two items on halves.');

    // On the screen, side by side.
    $this->actingAs($this->platformOwner, 'platform');
    Livewire::test(AiRoutesPage::class)
        ->callAction('compare', ['target' => 'practice_items|text', 'a' => [['engine' => $a->id]], 'b' => [['engine' => $b->id]], 'cases' => [$case->id]])
        ->assertHasNoActionErrors()->assertSee('from a')->assertSee('Engine A')->assertSee(__('shared::ai.failed.unavailable'));
});

it('still runs on the server\'s settings with no engines, and imports them as engines that change nothing', function () {
    config(['platform.ai.driver' => 'openai']);
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(gatewayAnswer(['items' => ['legacy']]))]);
    expect(PlatformAcademyAi::configured(AiFeature::PracticeItems))->toBeTrue()
        ->and(app(LanguageModel::class)->json(AiFeature::PracticeItems, gatewayPrompt())->data)->toBe(['items' => ['legacy']]);
    $row = DB::table('ai_usage')->sole();
    expect($row->provider)->toBe('openai')->and($row->engine_id)->toBeNull()->and($row->latency_ms)->toBeInt();
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-settings') && $r['model'] === 'gpt-settings');

    $made = app(ImportAiEngines::class)->handle($this->platformOwner);
    expect(array_map(fn (AiEngine $e) => $e->name, $made))->toBe([__('platform::services.names.anthropic'), __('platform::services.names.openai'), __('platform::services.names.elevenlabs')])
        ->and(AiRoute::query()->where('scope', 'default')->count())->toBe(6);   // five helpers' words, two of them hear
    $route = app(Routes::class)->resolve('practice_items', 'text');
    expect($route->scope)->toBe('default')->and($route->steps[0]->engine->key)->toBe('sk-settings')->and($route->steps[0]->engine->modelBest)->toBe('gpt-settings')
        ->and(app(Routes::class)->resolve('recitation_check', 'speech')->steps[0]->engine->key)->toBe('a-speech-key');
    expect(fn () => app(ImportAiEngines::class)->handle($this->platformOwner))->toThrow(ValidationException::class);

    // AI_DRIVER=off still turns every helper off; the demo still answers with no engine asked.
    config(['platform.ai.driver' => 'off']);
    expect(gatewayReason(fn () => app(LanguageModel::class)->json(AiFeature::PracticeItems, gatewayPrompt())))->toBe('off');
    config(['platform.ai.driver' => 'demo']);
    expect(app(LanguageModel::class)->json(AiFeature::PracticeItems, new AiPrompt('s', 'u', [], demo: ['items' => ['demo']]))->data)->toBe(['items' => ['demo']]);
});

it('saves routes only for registered capabilities, engines that do the work, and scopes that exist', function () {
    $words = gatewayEngine('Words engine', 'words.example.com');
    $ears = app(SaveAiEngine::class)->handle($this->platformOwner, null, ['name' => 'Ears', 'kind' => 'elevenlabs_stt', 'api_key' => 'xi-key', 'model_speech' => 'scribe_v2', 'enabled' => true]);
    $refused = fn (callable $call) => expect($call)->toThrow(ValidationException::class);

    $refused(fn () => gatewayRoute('nobody_registered_this', 'default', [$words]));
    $refused(fn () => gatewayRoute('practice_items', 'default', [$ears]));                 // ears do not write
    $refused(fn () => gatewayRoute('practice_items', 'default', [$words], 'speech'));     // practice never hears
    $refused(fn () => gatewayRoute('practice_items', 'plan:nope', [$words]));
    $refused(fn () => gatewayRoute('practice_items', 'academy:'.Str::uuid(), [$words]));
    $refused(fn () => gatewayRoute('practice_items', 'default', [$words, $words]));
    $refused(fn () => gatewayRoute('practice_items', 'default', [$words], split: 120, second: [$words]));
    gatewayRoute('recitation_check', 'default', [$ears], 'speech');
    expect(AiRoute::query()->count())->toBe(1)
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.ai_route_saved')->value('data'))->toContain('Ears');
});
