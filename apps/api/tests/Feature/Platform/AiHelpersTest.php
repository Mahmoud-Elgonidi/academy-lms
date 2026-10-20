<?php

use Anthropic\Beta\Messages\BetaMessage;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Platform\Ai\AiSlots;
use Modules\Platform\Ai\ClaudeLanguageModel;
use Modules\Platform\Ai\DemoLanguageModel;
use Modules\Platform\Ai\DemoSpeechToText;
use Modules\Platform\Ai\ElevenLabsSpeechToText;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Filament\Pages\AiSettingsPage;
use Modules\Platform\Health\AssistBeat;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\NoAi;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;
use Modules\SharedKernel\Plans\FeatureNotInPlan;

/*
 * The AI helpers' foundation (docs/architecture/02, "المساعد الآلي"): each
 * academy turns each helper on (all off at first, only on a plan with the AI
 * area); Claude writes and ElevenLabs listens with the platform's keys; what
 * is kept is only how many calls and how they went; a helper that cannot run
 * says why in the reader's words; and the platform sees the providers on its
 * health page.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'en']);
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    config([
        'platform.ai.driver' => 'claude',
        'platform.ai.key' => 'a-key-for-the-test',
        'platform.ai.speech.key' => 'a-speech-key-for-the-test',
        'platform.ai.daily_cap' => 500,
    ]);
    Cache::forget('health:ai');
    app()->setLocale('en');
});

afterEach(fn () => Cache::forget('health:ai'));

/** A recording on the disk, for the ears. */
function aiClip(string $bytes = 'RIFF....WAVEfmt '): AudioClip
{
    $path = tempnam(sys_get_temp_dir(), 'clip');
    file_put_contents($path, $bytes);

    return new AudioClip($path, 'audio/wav', 'clip.wav');
}

function aiReason(callable $call): string
{
    try {
        $call();
    } catch (AiFailed $e) {
        return $e->reason;
    }

    return 'none';
}

/** Claude's answer as the API sends it (the model's name stays out of the repository). */
function aiAnswer(string $text, string $stop = 'end_turn'): BetaMessage
{
    return BetaMessage::fromArray([
        'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'the-writer',
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stop, 'stop_sequence' => null,
        'usage' => ['input_tokens' => 900, 'output_tokens' => 300, 'cache_read_input_tokens' => 800, 'cache_creation_input_tokens' => 100],
    ]);
}

it('lets a helper run only when the academy turned it on, its plan has the area, the keys are set and the day has room', function () {
    $ai = app(AcademyAi::class);
    expect($ai)->toBeInstanceOf(PlatformAcademyAi::class)
        ->and(collect(AiFeature::cases())->map(fn (AiFeature $f) => $ai->why($f))->unique()->all())->toBe(['off']);

    app(SaveAiSettings::class)->handle(['practice_items' => true, 'report_draft' => true, 'nonsense' => true], $this->owner->id);
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue()
        ->and($ai->on(AiFeature::ReportDraft))->toBeTrue()
        ->and($ai->why(AiFeature::RecitationCheck))->toBe('off')
        ->and(AiSettings::current()->getAttributes())->not->toHaveKey('nonsense');

    // The keys each one needs: the listening ones need the speech key too.
    config(['platform.ai.speech.key' => '']);
    expect($ai->why(AiFeature::ReportDraft))->toBe('not_configured')->and($ai->on(AiFeature::PracticeItems))->toBeTrue();
    config(['platform.ai.key' => '']);
    expect($ai->why(AiFeature::PracticeItems))->toBe('not_configured')
        ->and(aiReason(fn () => $ai->require(AiFeature::PracticeItems)))->toBe('not_configured');

    // The demo's fixed answers need no key, and never run in production.
    config(['platform.ai.driver' => 'demo']);
    expect($ai->on(AiFeature::ReportDraft))->toBeTrue()
        ->and(app(LanguageModel::class))->toBeInstanceOf(DemoLanguageModel::class)
        ->and(app(SpeechToText::class))->toBeInstanceOf(DemoSpeechToText::class);
    app()['env'] = 'production';
    try {
        expect($ai->why(AiFeature::PracticeItems))->toBe('not_configured')
            ->and(app(LanguageModel::class))->toBeInstanceOf(ClaudeLanguageModel::class);
    } finally {
        app()['env'] = 'testing';
    }

    // Each academy's calls a day, counted from the day's start.
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'k', 'platform.ai.daily_cap' => 2]);
    AiUsage::note(AiFeature::PracticeItems, 'anthropic', 'm', ['input' => 10, 'output' => 5], 'ok');
    AiUsage::note(AiFeature::ReportDraft, 'anthropic', 'm', [], 'unavailable');
    expect($ai->why(AiFeature::PracticeItems))->toBe('limit');
    $this->travelTo(now()->addDay()->startOfDay()->addMinute());
    expect($ai->on(AiFeature::PracticeItems))->toBeTrue();

    // The platform turns them all off at once.
    config(['platform.ai.driver' => 'off']);
    expect($ai->why(AiFeature::PracticeItems))->toBe('off');
    config(['platform.ai.driver' => 'claude']);

    // A plan without the AI area: off, and the switch cannot be saved.
    $plan = SaasPlan::query()->create(['code' => 'p-'.Str::lower(Str::random(6)), 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    app()->forgetScopedInstances();
    $this->actAsTenant($this->academy);
    expect(app(AcademyAi::class)->why(AiFeature::PracticeItems))->toBe('off')
        ->and(fn () => app(SaveAiSettings::class)->handle(['practice_items' => true], $this->owner->id))->toThrow(FeatureNotInPlan::class);

    // No academy, no helper.
    $this->clearTenant();
    expect(app(AcademyAi::class)->why(AiFeature::PracticeItems))->toBe('off');
});

it('fails closed where the platform binds nothing', function () {
    $none = new NoAi;

    expect($none->on(AiFeature::PracticeItems))->toBeFalse()
        ->and(aiReason(fn () => $none->require(AiFeature::PracticeItems)))->toBe('off')
        ->and(aiReason(fn () => $none->json(AiFeature::PracticeItems, new AiPrompt('s', 'u', []))))->toBe('not_configured')
        ->and(aiReason(fn () => $none->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('not_configured');
});

it('keeps the switches to the owner and admin, and audits the helpers that are on, nothing else', function () {
    expect(Gate::forUser($this->owner)->allows(Ability::ManageAi->value))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows(Ability::ManageAi->value))->toBeTrue()
        ->and(Gate::forUser($this->teacher)->allows(Ability::ManageAi->value))->toBeFalse()
        ->and(Gate::forUser($this->accountant)->allows(Ability::ManageAi->value))->toBeFalse();

    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');
    Livewire::test(AiSettingsPage::class)
        ->assertSee(__('platform::ai.settings.title'))
        ->assertSee(__('platform::ai.settings.helpers.recitation_check.data'))
        ->assertFormSet(['practice_items' => false, 'report_draft' => false, 'recitation_check' => false, 'digest_wording' => false])
        ->fillForm(['practice_items' => true, 'digest_wording' => true])
        ->call('save')->assertHasNoFormErrors();

    expect(AiSettings::current())->toMatchArray(['practice_items' => true, 'report_draft' => false, 'digest_wording' => true, 'updated_by' => $this->owner->id]);
    $log = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.ai_settings_saved')->sole();
    expect(json_decode($log->data, true))->toBe(['helpers' => ['practice_items', 'digest_wording']]);

    // Without a key the page says the helper is not set up, instead of what it would send.
    config(['platform.ai.speech.key' => '']);
    Livewire::test(AiSettingsPage::class)
        ->assertSee(__('platform::ai.settings.not_configured'))
        ->assertDontSee(__('platform::ai.settings.helpers.recitation_check.data'));

    $this->actingAs($this->accountant);
    Livewire::test(AiSettingsPage::class)->assertForbidden();
});

it('keeps only how many calls and how they went, never who asked or what was said, and never changes it', function () {
    AiUsage::note(AiFeature::ReportDraft, 'elevenlabs', 'scribe', ['audio_seconds' => 42], 'ok');
    $row = DB::table('ai_usage')->sole();

    expect(array_keys((array) $row))->toEqualCanonicalizing(['id', 'academy_id', 'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'audio_seconds', 'outcome', 'created_at'])
        ->and($row->audio_seconds)->toBe(42)
        ->and(fn () => DB::table('ai_usage')->where('id', $row->id)->update(['outcome' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('ai_usage')->where('id', $row->id)->delete())->toThrow(QueryException::class);

    // Another academy sees none of it.
    $this->actAsTenant($this->createAcademy(['slug' => 'rawda']));
    expect(DB::table('ai_usage')->count())->toBe(0)->and(AiUsage::today())->toBe(0);
});

it('asks Claude for the prompt\'s schema with the system prompt cached, and a fallback only where the model has one', function () {
    $prompt = new AiPrompt('the rules', 'the lesson', ['type' => 'object'], tier: 'best', effort: 'medium', maxTokens: 4000);

    $best = ClaudeLanguageModel::request($prompt, 'claude-opus-5-5', ['claude-opus-5-5', 'claude-sonnet-5-5']);
    expect($best)->toMatchArray([
        'maxTokens' => 4000,
        'model' => 'claude-opus-5-5',
        'messages' => [['role' => 'user', 'content' => 'the lesson']],
        'system' => [['type' => 'text', 'text' => 'the rules', 'cacheControl' => ['type' => 'ephemeral']]],
        'outputConfig' => ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => ['type' => 'object']]],
        'fallbacks' => 'default',
    ])->and($best['betas'])->toHaveCount(1);

    $fast = ClaudeLanguageModel::request(new AiPrompt('s', 'u', [], effort: 'max'), 'claude-haiku-5-5', ['claude-opus-5-5']);
    expect($fast)->not->toHaveKeys(['fallbacks', 'betas'])
        ->and($fast['outputConfig']['effort'])->toBe('low');

    config(['platform.ai.key' => '']);
    expect(aiReason(fn () => app(ClaudeLanguageModel::class)->json(AiFeature::PracticeItems, $prompt)))->toBe('not_configured');
});

it('reads Claude\'s answer as data with its tokens, or says why it cannot be used', function () {
    $answer = ClaudeLanguageModel::read(aiAnswer('{"items": [{"q": "١ + ١"}]}'));
    expect($answer->data)->toBe(['items' => [['q' => '١ + ١']]])
        ->and($answer->model)->toBe('the-writer')
        ->and($answer->usage)->toMatchArray(['input' => 900, 'output' => 300, 'cache_read' => 800, 'cache_write' => 100]);

    $read = fn (string $text, string $stop = 'end_turn') => fn () => ClaudeLanguageModel::read(aiAnswer($text, $stop));
    expect(aiReason($read('{}', 'refusal')))->toBe('refused')
        ->and(aiReason($read('{"items": [', 'max_tokens')))->toBe('too_long')
        ->and(aiReason($read('Here are the questions')))->toBe('unreadable')
        ->and(aiReason($read('[1, 2]')))->toBe('unreadable');
});

it('hears a recording through ElevenLabs without being told what it should say, and keeps the words with their times', function () {
    Http::fake(['api.elevenlabs.io/v1/speech-to-text*' => Http::response([
        'language_code' => 'ara', 'text' => 'بسم الله الرحمن الرحيم',
        'words' => [
            ['text' => 'بسم', 'start' => 0.1, 'end' => 0.5, 'type' => 'word'],
            ['text' => ' ', 'start' => 0.5, 'end' => 0.6, 'type' => 'spacing'],
            ['text' => 'الله', 'start' => 0.6, 'end' => 1.0, 'type' => 'word'],
            ['text' => '(cough)', 'start' => 1.0, 'end' => 1.2, 'type' => 'audio_event'],
            ['text' => 'الرحمن', 'start' => 1.2, 'end' => 1.8, 'type' => 'word'],
            ['text' => 'الرحيم', 'start' => 1.9, 'end' => 2.6, 'type' => 'word'],
        ],
    ])]);

    $heard = app(ElevenLabsSpeechToText::class)->transcribe(AiFeature::RecitationCheck, aiClip());

    expect($heard->text)->toBe('بسم الله الرحمن الرحيم')
        ->and(array_map(fn ($w) => $w->text, $heard->words))->toBe(['بسم', 'الله', 'الرحمن', 'الرحيم'])
        ->and($heard->seconds)->toBe(2.6);
    Http::assertSent(function (Request $request) {
        $fields = collect($request->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

        return $request->hasHeader('xi-api-key', 'a-speech-key-for-the-test')
            && ! str_contains($request->url(), 'enable_logging')
            && $fields['language_code'] === 'ara'
            && $fields['timestamps_granularity'] === 'word'
            && $fields->has('file') && $fields->has('model_id')
            && ! $fields->keys()->intersect(['text', 'expected', 'prompt', 'keyterms'])->count();
    });
    expect(DB::table('ai_usage')->sole())->toMatchArray(['provider' => 'elevenlabs', 'feature' => 'recitation_check', 'audio_seconds' => 3, 'outcome' => 'ok'])
        ->and(ServiceHealth::last('elevenlabs'))->toHaveKey('ok');

    // Where the account allows it, nothing is kept there.
    config(['platform.ai.speech.zero_retention' => true]);
    app(ElevenLabsSpeechToText::class)->transcribe(AiFeature::RecitationCheck, aiClip());
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'enable_logging=false'));
});

it('calls a recording with nothing in it unheard, and a provider that does not answer unavailable', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['text' => '', 'words' => []])
        ->push(['detail' => ['status' => 'invalid_audio']], 422)
        ->push('down', 500)
        ->push('down', 503)
        ->push('down', 502)]);
    $ears = app(ElevenLabsSpeechToText::class);

    expect(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('unheard')
        ->and(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('unheard')
        ->and(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('unavailable')
        ->and(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip(''))))->toBe('unheard');   // an empty file never leaves
    expect(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('unavailable')
        ->and(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('unavailable');
    Http::assertSentCount(5);
    expect(ServiceHealth::last('elevenlabs'))->toMatchArray(['in_a_row' => 3, 'problem' => 'down', 'code' => 'HTTP 502'])
        ->and(DB::table('ai_usage')->pluck('outcome')->sort()->values()->all())->toBe(['unavailable', 'unavailable', 'unavailable', 'unheard', 'unheard']);

    config(['platform.ai.speech.key' => '']);
    expect(aiReason(fn () => $ears->transcribe(AiFeature::ReportDraft, aiClip())))->toBe('not_configured');
});

it('tells the next person the helper is busy while every slot is taken', function () {
    config(['platform.ai.concurrent' => 1]);
    $slots = app(AiSlots::class);

    expect($slots->hold(fn () => aiReason(fn () => $slots->hold(fn () => 'ran'))))->toBe('busy')
        ->and($slots->hold(fn () => 'ran'))->toBe('ran');
});

it('answers a helper that cannot run as a problem in the reader\'s words, never the provider\'s', function () {
    Route::middleware('api')->get('api/v1/_ai-test/{reason}', fn (string $reason) => throw new AiFailed($reason, 'the provider said something'));
    $this->clearTenant();

    foreach (['off' => [403, 'ai-off'], 'limit' => [429, 'ai-limit'], 'busy' => [503, 'ai-unavailable'], 'unheard' => [422, 'ai-unheard'], 'refused' => [422, 'ai-no-answer']] as $reason => [$status, $type]) {
        $this->getJson("api/v1/_ai-test/{$reason}")->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', $type)
            ->assertJsonPath('reason', $reason)
            ->assertJsonPath('detail', __('shared::ai.failed.'.$reason))
            ->assertDontSee('the provider said something');
    }
});

it('shows the platform the helpers\' queue and providers on its health page, off while no key is set', function () {
    config(['platform.ai.key' => '', 'platform.ai.speech.key' => '', 'platform.games.designer.key' => '']);
    expect(app(SystemHealth::class)->checks())->not->toHaveKey('service_anthropic')
        ->and(app(SystemHealth::class)->checks()['assist']['status'])->toBe('off');

    config(['platform.ai.key' => 'k']);
    expect(app(SystemHealth::class)->checks()['assist']['status'])->toBe('fail');
    (new AssistBeat(now()->getTimestamp() - 2))->handle();
    expect(app(SystemHealth::class)->checks()['assist']['status'])->toBe('ok')
        ->and(Heartbeats::last('assist')['lag'])->toBeGreaterThanOrEqual(2)
        ->and(app(SystemHealth::class)->checks()['service_anthropic'])->toMatchArray(['status' => 'ok', 'value' => __('platform::services.used.never')]);

    // Busy now and then: a word on the page after three in a row, the provider's code only, never anyone's.
    foreach (range(1, 3) as $i) {
        ServiceHealth::failed('anthropic', ServiceProblem::Down, 'overloaded_error from teacher@noor.test');
    }
    $check = app(SystemHealth::class)->checks()['service_anthropic'];
    expect($check['status'])->toBe('warn')->and($check['value'])->toContain(__('platform::services.short.down'))->toContain('overloaded_error')
        ->not->toContain('teacher@noor.test')
        ->and(app(SystemHealth::class)->checks()['assist']['status'])->toBe('ok');
    ServiceHealth::ok('anthropic');
    expect(app(SystemHealth::class)->checks()['service_anthropic']['status'])->toBe('ok');

    // The credit ran out: down at once, and what to do.
    ServiceHealth::failed('anthropic', ServiceProblem::Quota, 'billing_error');
    $check = app(SystemHealth::class)->checks()['service_anthropic'];
    expect($check['status'])->toBe('fail')->and($check['hint'])->toContain(__('platform::services.todo.quota', ['service' => 'Claude (Anthropic)']));
});
