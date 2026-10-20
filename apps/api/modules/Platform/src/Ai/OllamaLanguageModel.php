<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\PendingRequest;
use Modules\Platform\Ai\Faults\OllamaFaults;
use Modules\Platform\Ai\Support\JsonAnswer;
use Modules\Platform\Ai\Support\ModelCall;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * The helpers' words through the platform's own Ollama server (/api/chat):
 * the answer held to the prompt's JSON schema (format), in one piece, at
 * temperature 0. Nothing leaves the platform's servers; a key is sent only
 * when a proxy in front of it asks for one (OLLAMA_API_KEY).
 */
final class OllamaLanguageModel implements LanguageModel
{
    public function __construct(private readonly ModelCall $call) {}

    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        $provider = AiProvider::Ollama;
        $model = $provider->model($prompt->tier);
        if (! $provider->hasCredentials() || $model === '') {
            throw new AiFailed('not_configured');
        }
        $request = self::request($prompt, $model);

        return $this->call->run($feature, $provider, $model,
            send: fn (PendingRequest $http) => ($provider->key() !== '' ? $http->withToken($provider->key()) : $http)
                ->post($provider->baseUrl().'/api/chat', $request),
            read: fn (array $body) => self::read($body, $model),
            usage: fn (array $body) => self::usage($body),
            fault: fn (int $status, mixed $body) => OllamaFaults::of($status, $body),
        );
    }

    /** @return array<string, mixed> */
    public static function request(AiPrompt $prompt, string $model): array
    {
        return [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $prompt->system], ['role' => 'user', 'content' => $prompt->user]],
            'format' => $prompt->schema,
            'stream' => false,
            'options' => ['num_predict' => $prompt->maxTokens, 'temperature' => 0],
        ];
    }

    /** @param  array<string, mixed>  $body */
    public static function read(array $body, string $model): AiAnswer
    {
        if (($body['done_reason'] ?? null) === 'length') {
            throw new AiFailed('too_long');
        }

        return new AiAnswer(JsonAnswer::object((string) data_get($body, 'message.content', '')), (string) ($body['model'] ?? $model), self::usage($body));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{input?: int, output?: int}
     */
    public static function usage(array $body): array
    {
        return array_filter(['input' => $body['prompt_eval_count'] ?? null, 'output' => $body['eval_count'] ?? null], fn ($v) => $v !== null);
    }
}
