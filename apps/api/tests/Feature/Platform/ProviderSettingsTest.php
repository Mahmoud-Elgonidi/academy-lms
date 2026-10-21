<?php

use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Platform\Actions\SavePlatformSettings;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\ModelCatalog;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\Rules\OllamaAddress;

/*
 * Choosing who writes the helpers' words in Platform settings: the provider
 * first, then its key and its models from its own list (no typing but for
 * "another name…"), each provider keeping its own choices; the lists come
 * from the provider with the key typed or in force, kept a few minutes under
 * a name that never holds the key.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    $this->owner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@company.example', 'password' => 'a-long-password']);
    $this->owner->forceFill(['is_owner' => true])->save();
    $this->owner->saveAppAuthenticationSecret('LMSPROVIDERSTESTCONSOLE2FASECRE');
    DB::connection('pgsql_platform')->table('platform_settings')->delete();
    app(PlatformSettings::class)->forget();
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'sk-ant-in-force', 'platform.ai.openai.key' => null, 'platform.ai.gemini.key' => null, 'platform.ai.ollama.url' => null]);
    app()->setLocale('en');
});

function fakeModelLists(): void
{
    Http::fake([
        'api.anthropic.com/v1/models*' => fn (Request $r) => str_contains($r->url(), 'after_id=claude-b')
            ? Http::response(['data' => [['id' => 'claude-c', 'display_name' => 'Claude C']], 'has_more' => false])
            : Http::response(['data' => [['id' => 'claude-a', 'display_name' => 'Claude A'], ['id' => 'claude-b', 'display_name' => 'Claude B']], 'has_more' => true, 'last_id' => 'claude-b']),
        'api.openai.com/v1/models' => Http::response(['data' => [
            ['id' => 'gpt-4.1-mini', 'created' => 100], ['id' => 'gpt-5.1', 'created' => 300], ['id' => 'text-embedding-3-large', 'created' => 200],
            ['id' => 'gpt-4o-realtime-preview', 'created' => 250], ['id' => 'dall-e-3', 'created' => 50], ['id' => 'o4-mini', 'created' => 200],
            ['id' => 'gpt-image-1', 'created' => 280], ['id' => 'whisper-1', 'created' => 10],
        ]]),
        'generativelanguage.googleapis.com/*' => fn (Request $r) => str_contains($r->url(), 'pageToken=next')
            ? Http::response(['models' => [['name' => 'models/gemini-flash', 'displayName' => 'Gemini Flash', 'supportedGenerationMethods' => ['generateContent']]]])
            : Http::response(['models' => [
                ['name' => 'models/gemini-pro', 'displayName' => 'Gemini Pro', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
                ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
                ['name' => 'models/imagen-4', 'supportedGenerationMethods' => ['predict']],
            ], 'nextPageToken' => 'next']),
        'ollama:11434/api/tags' => Http::response(['models' => [
            ['name' => 'qwen3:14b', 'details' => ['parameter_size' => '14.8B', 'quantization_level' => 'Q4_K_M']],
            ['name' => 'nomic-embed-text:latest'],
        ]]),
    ]);
}

it('lists each provider\'s models that write, from the provider, with the key typed or in force', function () {
    fakeModelLists();
    $catalog = app(ModelCatalog::class);

    expect($catalog->list(AiProvider::Claude)->options)->toBe(['claude-a' => 'Claude A (claude-a)', 'claude-b' => 'Claude B (claude-b)', 'claude-c' => 'Claude C (claude-c)'])
        ->and(array_keys($catalog->list(AiProvider::OpenAi, 'sk-typed')->options))->toBe(['gpt-5.1', 'o4-mini', 'gpt-4.1-mini'])
        ->and($catalog->list(AiProvider::Gemini, 'g-typed')->options)->toBe(['gemini-pro' => 'Gemini Pro (gemini-pro)', 'gemini-flash' => 'Gemini Flash (gemini-flash)'])
        ->and($catalog->list(AiProvider::Ollama, null, 'http://ollama:11434')->options)->toBe(['qwen3:14b' => 'qwen3:14b · 14.8B Q4_K_M']);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.anthropic.com/v1/models') && $r->hasHeader('x-api-key', 'sk-ant-in-force') && $r->hasHeader('anthropic-version'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.openai.com') && $r->hasHeader('Authorization', 'Bearer sk-typed'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage') && $r->hasHeader('x-goog-api-key', 'g-typed') && ! str_contains($r->url(), 'g-typed'));

    // Kept: asked once; another key is another list; the name it is kept under never holds the key.
    $sent = count(Http::recorded());
    $catalog->list(AiProvider::OpenAi, 'sk-typed');
    expect(count(Http::recorded()))->toBe($sent)
        ->and($catalog->cacheKey(AiProvider::OpenAi, 'sk-typed', 'https://api.openai.com/v1'))->not->toContain('sk-typed');
    $catalog->list(AiProvider::OpenAi, 'sk-another');
    expect(count(Http::recorded()))->toBe($sent + 1);
});

it('says why a list cannot be had, keeps that a minute, and asks nobody without a key', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'invalid_api_key']], 401)]);
    $catalog = app(ModelCatalog::class);

    $list = $catalog->list(AiProvider::OpenAi, 'sk-wrong');
    expect($list->options)->toBe([])->and($list->problem?->value)->toBe('key')->and($list->code)->toBe('invalid_api_key')
        ->and($catalog->cached(AiProvider::OpenAi, 'sk-wrong')?->problem?->value)->toBe('key');

    expect($catalog->list(AiProvider::Gemini)->code)->toBe('missing');
    Http::assertSentCount(1);
});

it('shows the chosen provider\'s key and models only, and its list to choose from', function () {
    fakeModelLists();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    $page = Livewire::test(PlatformSettingsPage::class)
        ->assertFormFieldVisible('ai_model')->assertFormFieldHidden('openai_key')->assertFormFieldVisible('game_designer_model')
        ->fillForm(['ai_driver' => 'openai'])
        ->assertFormFieldVisible('openai_key')->assertFormFieldVisible('openai_model')->assertFormFieldHidden('ai_model')
        ->fillForm(['openai_key' => 'sk-typed-not-saved']);

    $options = fn () => $page->instance()->form->getFlatFields(withHidden: true)['openai_model']->getOptions();
    expect(array_keys($options()))->toBe(['gpt-5.1', 'o4-mini', 'gpt-4.1-mini', ModelCatalog::CUSTOM]);

    // Another name, by hand.
    $page->fillForm(['openai_model' => ModelCatalog::CUSTOM])->assertFormFieldVisible('custom.openai_model')
        ->fillForm(['custom' => ['openai_model' => 'ft:gpt-4.1-mini:academy::abc123'], 'openai_fast_model' => 'gpt-4.1-mini'])
        ->call('save')->assertHasNoFormErrors();
    expect(app(PlatformSettings::class)->values())->toMatchArray([
        'ai_driver' => 'openai', 'openai_key' => 'sk-typed-not-saved', 'openai_model' => 'ft:gpt-4.1-mini:academy::abc123', 'openai_fast_model' => 'gpt-4.1-mini',
    ]);

    // A model not (or no longer) in the list shows as itself.
    $page = Livewire::test(PlatformSettingsPage::class);
    expect($page->instance()->form->getFlatFields(withHidden: true)['openai_model']->getOptions())->toHaveKey('ft:gpt-4.1-mini:academy::abc123', 'ft:gpt-4.1-mini:academy::abc123 (in use)');
});

it('shows Claude\'s models before a key is put in, and Anthropic\'s own list once it works', function () {
    config(['platform.ai.key' => null, 'platform.ai.models.best' => null, 'platform.ai.models.fast' => null]);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    $page = Livewire::test(PlatformSettingsPage::class)->call('warmModels');
    $options = fn (string $key) => $page->instance()->form->getFlatFields(withHidden: true)[$key]->getOptions();
    expect($options('ai_model'))->toHaveKeys(['claude-fable-5-1', 'claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', ModelCatalog::CUSTOM])
        ->and($options('ai_model')['claude-sonnet-5-5'])->toBe('Claude Sonnet 5.5 (claude-sonnet-5-5)')
        ->and(array_key_last($options('ai_model')))->toBe(ModelCatalog::CUSTOM)
        ->and($options('game_designer_model'))->toHaveKey('claude-opus-5-5');
    $page->assertSee('Claude (Anthropic)\'s main models. Put the key in to see every model your account has.');
    Http::assertNothingSent();

    // GPT lists its models only for a key: nothing to guess before it.
    $page->fillForm(['ai_driver' => 'openai']);
    expect(array_keys($options('openai_model')))->toBe([ModelCatalog::CUSTOM]);

    // The key typed: Anthropic's own list, and only it.
    fakeModelLists();
    $page->fillForm(['ai_driver' => 'claude', 'anthropic_key' => 'sk-ant-typed'])->call('warmModels');
    expect(array_keys($options('ai_model')))->toBe(['claude-a', 'claude-b', 'claude-c', ModelCatalog::CUSTOM]);
});

it('keeps another provider\'s choices while it is not the one chosen', function () {
    fakeModelLists();
    app(SavePlatformSettings::class)->handle($this->owner, ['ai_model' => 'claude-c', 'gemini_key' => 'g-saved']);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');

    Livewire::test(PlatformSettingsPage::class)->fillForm(['ai_driver' => 'ollama', 'ollama_url' => 'http://ollama:11434', 'ollama_model' => 'qwen3:14b'])
        ->call('save')->assertHasNoFormErrors();

    expect(app(PlatformSettings::class)->values())->toMatchArray(['ai_model' => 'claude-c', 'gemini_key' => 'g-saved', 'ai_driver' => 'ollama', 'ollama_model' => 'qwen3:14b']);
});

it('takes no model name with spaces, and the demo only from the server', function () {
    expect(fn () => app(SavePlatformSettings::class)->handle($this->owner, ['openai_model' => 'gpt 5']))->toThrow(ValidationException::class)
        ->and(fn () => app(SavePlatformSettings::class)->handle($this->owner, ['ai_driver' => 'demo']))->toThrow(ValidationException::class);

    config(['platform.ai.driver' => 'demo']);
    Filament::setCurrentPanel('console');
    $this->actingAs($this->owner, 'platform');
    Livewire::test(PlatformSettingsPage::class)->assertSee('Server: Demo answers (no key)')->assertSee(__('platform::settings.ai_notes.demo'));
});

it('takes Ollama in clear only on a private network, and never a password in its address', function (string $url, bool $ok) {
    $fails = false;
    (new OllamaAddress)->validate('ollama_url', $url, function () use (&$fails) {
        $fails = true;
    });

    expect($fails)->toBe(! $ok);
})->with([
    'https anywhere' => ['https://ai.example.com', true],
    'a container\'s name' => ['http://ollama:11434', true],
    'this machine' => ['http://127.0.0.1:11434', true],
    'a private network' => ['http://10.0.0.5:11434', true],
    'docker\'s host' => ['http://host.docker.internal:11434', true],
    'in clear over the internet' => ['http://ai.example.com:11434', false],
    'a public address in clear' => ['http://8.8.8.8:11434', false],
    'a password in it' => ['https://user:pass@ai.example.com', false],
]);
