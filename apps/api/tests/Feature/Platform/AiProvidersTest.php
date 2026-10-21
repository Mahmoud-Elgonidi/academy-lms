<?php

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\ClaudeLanguageModel;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\Platform\Ai\GeminiLanguageModel;
use Modules\Platform\Ai\OllamaLanguageModel;
use Modules\Platform\Ai\OpenAiLanguageModel;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Ai\Support\JsonAnswer;
use Modules\Platform\Ai\Support\ModelCall;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Health\ServiceHealth;

/*
 * Who writes the helpers' words is the platform's choice (AI_DRIVER, from
 * Platform settings): Claude, GPT, Gemini or its own Ollama server, each with
 * its own key and models. Each is asked the same way (the prompt's schema
 * held, nothing kept where the provider allows it), answers the same shape,
 * counts in ai_usage under its name, and says why it would not in our words.
 */

beforeEach(function () {
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    config([
        'platform.ai.key' => 'a-claude-key',
        'platform.ai.openai' => ['key' => 'sk-openai-test', 'base_url' => 'https://api.openai.com/v1', 'models' => ['best' => 'gpt-5.1', 'fast' => 'gpt-4.1-mini']],
        'platform.ai.gemini' => ['key' => 'gemini-test-key', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'models' => ['best' => 'gemini-pro', 'fast' => '']],
        'platform.ai.ollama' => ['url' => 'http://ollama:11434', 'key' => '', 'models' => ['best' => 'qwen3:14b', 'fast' => '']],
    ]);
    foreach (['openai', 'gemini', 'ollama'] as $service) {
        ServiceHealth::forget($service);
    }
});

/** A prompt with an optional property, as the helpers' schemas have. */
function providerPrompt(string $tier = 'best'): AiPrompt
{
    return new AiPrompt(
        system: 'Answer with the JSON asked.',
        user: 'Two items.',
        schema: ['type' => 'object', 'properties' => [
            'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'kind' => ['type' => 'string', 'enum' => ['choice', 'pair']],
                'note' => ['type' => 'string'],
            ], 'required' => ['kind'], 'additionalProperties' => false]],
        ], 'required' => ['items'], 'additionalProperties' => false],
        tier: $tier,
        effort: 'medium',
        maxTokens: 900,
    );
}

function providerReason(callable $call): string
{
    try {
        $call();
    } catch (AiFailed $e) {
        return $e->reason;
    }

    return 'none';
}

it('asks GPT in strict structured outputs, keeps nothing there, and reads the same shape back', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
        'model' => 'gpt-5.1-2026-01-01',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => '{"items":[{"kind":"choice","note":null},{"kind":"pair","note":"x"}]}']]],
        'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 300, 'prompt_tokens_details' => ['cached_tokens' => 1000]],
    ])]);

    $answer = app(OpenAiLanguageModel::class)->json(AiFeature::PracticeItems, providerPrompt());

    expect($answer->data)->toBe(['items' => [['kind' => 'choice'], ['kind' => 'pair', 'note' => 'x']]])
        ->and($answer->model)->toBe('gpt-5.1-2026-01-01')
        ->and($answer->usage)->toBe(['input' => 200, 'output' => 300, 'cache_read' => 1000]);
    Http::assertSent(function (Request $r) {
        $item = $r['response_format']['json_schema']['schema']['properties']['items']['items'];

        return $r->hasHeader('Authorization', 'Bearer sk-openai-test')
            && $r['model'] === 'gpt-5.1' && $r['store'] === false && $r['max_completion_tokens'] === 900
            && $r['reasoning_effort'] === 'medium'
            && $r['response_format']['json_schema']['strict'] === true
            && $item['required'] === ['kind', 'note'] && $item['properties']['note']['type'] === ['string', 'null']
            && $r['messages'][0] === ['role' => 'system', 'content' => 'Answer with the JSON asked.'];
    });
    expect(DB::table('ai_usage')->sole())->toMatchArray(['provider' => 'openai', 'model' => 'gpt-5.1-2026-01-01', 'outcome' => 'ok'])
        ->and(ServiceHealth::last('openai'))->toHaveKey('ok');

    // A model that does not think before answering is not sent an effort.
    expect(OpenAiLanguageModel::request(providerPrompt(), 'gpt-4.1-mini', 'x'))->not->toHaveKey('reasoning_effort')
        ->and(OpenAiLanguageModel::reasons('o4-mini'))->toBeTrue()
        ->and(OpenAiLanguageModel::reasons('gpt-5-chat-latest'))->toBeFalse();
});

it('tells GPT\'s spent credit from a wrong key, a refusal and an answer cut short', function () {
    $gpt = app(OpenAiLanguageModel::class);
    $answer = fn (array $body, int $status = 200) => Http::fake(['*' => Http::response($body, $status)]);

    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{"items":']]]])
        ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => null, 'refusal' => 'I cannot help with that.']]]])
        ->push(['error' => ['code' => 'context_length_exceeded', 'message' => 'too long']], 400)
        ->push(['error' => ['code' => 'insufficient_quota', 'type' => 'insufficient_quota', 'message' => 'You exceeded your current quota']], 429)
        ->push(['error' => ['code' => 'invalid_api_key', 'message' => 'Incorrect API key provided: sk-open***test']], 401)]);

    expect(providerReason(fn () => $gpt->json(AiFeature::PracticeItems, providerPrompt())))->toBe('too_long')
        ->and(providerReason(fn () => $gpt->json(AiFeature::PracticeItems, providerPrompt())))->toBe('refused')
        ->and(providerReason(fn () => $gpt->json(AiFeature::PracticeItems, providerPrompt())))->toBe('too_long')
        ->and(ServiceHealth::failing('openai'))->toBeFalse();

    expect(providerReason(fn () => $gpt->json(AiFeature::PracticeItems, providerPrompt())))->toBe('unavailable')
        ->and(ServiceHealth::last('openai'))->toMatchArray(['problem' => 'quota', 'code' => 'insufficient_quota']);
    expect(providerReason(fn () => $gpt->json(AiFeature::PracticeItems, providerPrompt())))->toBe('unavailable')
        ->and(ServiceHealth::last('openai'))->toMatchArray(['problem' => 'key', 'code' => 'invalid_api_key']);

    // A request someone waits on is tried once.
    Http::assertSentCount(5);
    expect(DB::table('ai_usage')->pluck('outcome')->sort()->values()->all())->toBe(['refused', 'too_long', 'too_long', 'unavailable', 'unavailable']);
});

it('asks Gemini with the key in a header and the schema held, and reads its refusals', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push([
            'modelVersion' => 'gemini-pro-001',
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'thinking…', 'thought' => true], ['text' => '{"items":[{"kind":"pair"}]}']]]]],
            'usageMetadata' => ['promptTokenCount' => 500, 'candidatesTokenCount' => 40, 'thoughtsTokenCount' => 60, 'cachedContentTokenCount' => 100],
        ])
        ->push(['candidates' => [['finishReason' => 'SAFETY']]])
        ->push(['promptFeedback' => ['blockReason' => 'OTHER']])
        ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"items"']]]]]])
        ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid.', 'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'API_KEY_INVALID']]]], 400)
        ->push(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'details' => [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]], 429)]);
    $gemini = app(GeminiLanguageModel::class);

    $answer = $gemini->json(AiFeature::DigestWording, providerPrompt('fast'));
    expect($answer->data)->toBe(['items' => [['kind' => 'pair']]])
        ->and($answer->usage)->toBe(['input' => 400, 'output' => 100, 'cache_read' => 100]);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/models/gemini-pro:generateContent')   // the fast model falls back to the best
        && ! str_contains($r->url(), 'gemini-test-key') && $r->hasHeader('x-goog-api-key', 'gemini-test-key')
        && $r['generationConfig']['responseMimeType'] === 'application/json'
        && $r['generationConfig']['responseJsonSchema']['properties']['items']['type'] === 'array'
        && $r['systemInstruction']['parts'][0]['text'] === 'Answer with the JSON asked.');

    expect(providerReason(fn () => $gemini->json(AiFeature::DigestWording, providerPrompt())))->toBe('refused')
        ->and(providerReason(fn () => $gemini->json(AiFeature::DigestWording, providerPrompt())))->toBe('refused')
        ->and(providerReason(fn () => $gemini->json(AiFeature::DigestWording, providerPrompt())))->toBe('too_long')
        ->and(providerReason(fn () => $gemini->json(AiFeature::DigestWording, providerPrompt())))->toBe('unavailable')
        ->and(ServiceHealth::last('gemini'))->toMatchArray(['problem' => 'key', 'code' => 'API_KEY_INVALID'])
        ->and(providerReason(fn () => $gemini->json(AiFeature::DigestWording, providerPrompt())))->toBe('unavailable')
        ->and(ServiceHealth::last('gemini'))->toMatchArray(['problem' => 'quota', 'code' => 'RESOURCE_EXHAUSTED']);
});

it('asks the platform\'s own Ollama server, with a key only when one is set', function () {
    Http::fake(['ollama:11434/*' => Http::sequence()
        ->push(['model' => 'qwen3:14b', 'message' => ['role' => 'assistant', 'content' => "```json\n{\"items\":[]}\n```"], 'done' => true, 'done_reason' => 'stop', 'prompt_eval_count' => 300, 'eval_count' => 20])
        ->push(['error' => 'model "qwen3:14b" not found, try pulling it first'], 404)
        ->push(['model' => 'qwen3:14b', 'message' => ['content' => '{"items":[{"kind":"pai'], 'done_reason' => 'length'])
        ->push(['message' => ['content' => '{}'], 'done_reason' => 'stop'])]);
    $ollama = app(OllamaLanguageModel::class);

    expect($ollama->json(AiFeature::ReportDraft, providerPrompt())->data)->toBe(['items' => []]);
    Http::assertSent(fn (Request $r) => $r->url() === 'http://ollama:11434/api/chat' && ! $r->hasHeader('Authorization')
        && $r['format']['type'] === 'object' && $r['stream'] === false && $r['options']['num_predict'] === 900);

    expect(providerReason(fn () => $ollama->json(AiFeature::ReportDraft, providerPrompt())))->toBe('unavailable')
        ->and(ServiceHealth::last('ollama'))->toMatchArray(['problem' => 'model', 'scope' => 'qwen3:14b'])
        ->and(providerReason(fn () => $ollama->json(AiFeature::ReportDraft, providerPrompt())))->toBe('too_long');

    config(['platform.ai.ollama.key' => 'behind-a-proxy']);
    $ollama->json(AiFeature::ReportDraft, providerPrompt());
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer behind-a-proxy'));
});

it('takes the words from the provider chosen, and calls a provider without its key or model not set up', function () {
    foreach (['claude' => ClaudeLanguageModel::class, 'openai' => OpenAiLanguageModel::class, 'gemini' => GeminiLanguageModel::class, 'ollama' => OllamaLanguageModel::class] as $driver => $class) {
        config(['platform.ai.driver' => $driver]);
        expect(app(LanguageModel::class))->toBeInstanceOf($class)
            ->and(AiProvider::current()->configured())->toBeTrue();
    }

    // Ollama needs its address and a model, not a key.
    config(['platform.ai.driver' => 'ollama', 'platform.ai.ollama.key' => '']);
    expect(PlatformAcademyAi::configured(AiFeature::PracticeItems))->toBeTrue();
    config(['platform.ai.ollama.url' => '']);
    expect(PlatformAcademyAi::configured(AiFeature::PracticeItems))->toBeFalse()
        ->and(providerReason(fn () => app(OllamaLanguageModel::class)->json(AiFeature::PracticeItems, providerPrompt())))->toBe('not_configured');

    config(['platform.ai.driver' => 'openai', 'platform.ai.openai.models.best' => '']);
    expect(PlatformAcademyAi::configured(AiFeature::PracticeItems))->toBeFalse()
        ->and(AiProvider::OpenAi->model('fast'))->toBe('gpt-4.1-mini');

    // Off, or a demo on a server: no provider, so no words.
    config(['platform.ai.driver' => 'off']);
    expect(AiProvider::current())->toBeNull()->and(PlatformAcademyAi::configured(AiFeature::PracticeItems))->toBeFalse();
});

it('reads a JSON object, forgiving one fence, and nothing else', function () {
    expect(JsonAnswer::object("```json\n{\"a\":1}\n```"))->toBe(['a' => 1])
        ->and(JsonAnswer::object('{}'))->toBe([])
        ->and(providerReason(fn () => JsonAnswer::object('[1,2]')))->toBe('unreadable')
        ->and(providerReason(fn () => JsonAnswer::object('Here you go')))->toBe('unreadable');
});

it('tries again in the background for a moment\'s trouble, never for a spent credit or a wrong key', function () {
    $passing = new ReflectionMethod(ModelCall::class, 'passing');
    $fault = fn (int $status, mixed $body) => OpenAiFaults::of($status, $body);
    $error = fn (int $status, array $body) => new RequestException(new Response(new GuzzleResponse($status, [], (string) json_encode($body))));

    expect($passing->invoke(null, $error(503, []), $fault))->toBeTrue()
        ->and($passing->invoke(null, $error(429, ['error' => ['code' => 'rate_limit_exceeded']]), $fault))->toBeTrue()
        ->and($passing->invoke(null, new ConnectionException('reset'), $fault))->toBeTrue()
        ->and($passing->invoke(null, $error(429, ['error' => ['code' => 'insufficient_quota']]), $fault))->toBeFalse()
        ->and($passing->invoke(null, $error(401, ['error' => ['code' => 'invalid_api_key']]), $fault))->toBeFalse();
});
