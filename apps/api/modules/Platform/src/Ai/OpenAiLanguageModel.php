<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\PendingRequest;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\Platform\Ai\Support\JsonAnswer;
use Modules\Platform\Ai\Support\ModelCall;
use Modules\Platform\Ai\Support\StrictSchema;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * The helpers' words through OpenAI's Chat Completions (HTTP, no SDK): the
 * answer held to the prompt's schema in strict mode (StrictSchema), nothing
 * stored on OpenAI's side (store: false), and the reasoning effort only for
 * the models that take one. The key is the platform's (OPENAI_API_KEY).
 */
final class OpenAiLanguageModel implements LanguageModel
{
    public function __construct(private readonly ModelCall $call) {}

    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        $provider = AiProvider::OpenAi;
        $model = $provider->model($prompt->tier);
        if (! $provider->hasCredentials() || $model === '') {
            throw new AiFailed('not_configured');
        }
        $request = self::request($prompt, $model, $feature->value);

        return $this->call->run($feature, $provider, $model,
            send: fn (PendingRequest $http) => $http->withToken($provider->key())->post($provider->baseUrl().'/chat/completions', $request),
            read: fn (array $body) => self::read($body, $prompt->schema, $model),
            usage: fn (array $body) => self::usage($body),
            fault: fn (int $status, mixed $body) => OpenAiFaults::of($status, $body),
        );
    }

    /** @return array<string, mixed> */
    public static function request(AiPrompt $prompt, string $model, string $name): array
    {
        $request = [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $prompt->system], ['role' => 'user', 'content' => $prompt->user]],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $name, 'strict' => true, 'schema' => StrictSchema::of($prompt->schema)]],
            'max_completion_tokens' => $prompt->maxTokens,
            'store' => false,
        ];
        if (self::reasons($model)) {
            $request['reasoning_effort'] = in_array($prompt->effort, AiPrompt::EFFORTS, true) ? $prompt->effort : 'low';
        }

        return $request;
    }

    /** The models that think before answering take an effort; the others refuse one. */
    public static function reasons(string $model): bool
    {
        return preg_match('/^(o\d|gpt-5)/', $model) === 1 && ! str_contains($model, '-chat');
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $schema
     */
    public static function read(array $body, array $schema, string $model): AiAnswer
    {
        $choice = (array) data_get($body, 'choices.0', []);
        if (filled(data_get($choice, 'message.refusal')) || ($choice['finish_reason'] ?? null) === 'content_filter') {
            throw new AiFailed('refused');
        }
        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new AiFailed('too_long');
        }
        $data = StrictSchema::strip(JsonAnswer::object((string) data_get($choice, 'message.content', '')), $schema);

        return new AiAnswer($data, (string) ($body['model'] ?? $model), self::usage($body));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{input?: int, output?: int, cache_read?: int}
     */
    public static function usage(array $body): array
    {
        $cached = (int) data_get($body, 'usage.prompt_tokens_details.cached_tokens', 0);

        return array_filter([
            'input' => isset($body['usage']['prompt_tokens']) ? (int) $body['usage']['prompt_tokens'] - $cached : null,
            'output' => data_get($body, 'usage.completion_tokens'),
            'cache_read' => $cached ?: null,
        ], fn ($v) => $v !== null);
    }
}
