<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\PendingRequest;
use Modules\Platform\Ai\Faults\GeminiFaults;
use Modules\Platform\Ai\Support\JsonAnswer;
use Modules\Platform\Ai\Support\ModelCall;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;

/**
 * The helpers' words through Gemini's generateContent (HTTP, no SDK): the
 * answer held to the prompt's JSON schema (responseJsonSchema). The key is
 * the platform's (GEMINI_API_KEY, from a project with billing on, whose
 * prompts Google does not use), sent as a header, never in the address.
 */
final class GeminiLanguageModel implements LanguageModel
{
    /** A candidate stopped for one of these was refused. */
    private const REFUSED = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'IMAGE_SAFETY', 'LANGUAGE'];

    public function __construct(private readonly ModelCall $call) {}

    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        $provider = AiProvider::Gemini;
        $model = $provider->model($prompt->tier);
        if (! $provider->hasCredentials() || $model === '') {
            throw new AiFailed('not_configured');
        }
        $request = self::request($prompt);

        return $this->call->run($feature, $provider, $model,
            send: fn (PendingRequest $http) => $http->withHeaders(['x-goog-api-key' => $provider->key()])
                ->post($provider->baseUrl().'/models/'.rawurlencode($model).':generateContent', $request),
            read: fn (array $body) => self::read($body, $model),
            usage: fn (array $body) => self::usage($body),
            fault: fn (int $status, mixed $body) => GeminiFaults::of($status, $body),
        );
    }

    /** @return array<string, mixed> */
    public static function request(AiPrompt $prompt): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => $prompt->system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt->user]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $prompt->schema,
                'maxOutputTokens' => $prompt->maxTokens,
            ],
        ];
    }

    /** @param  array<string, mixed>  $body */
    public static function read(array $body, string $model): AiAnswer
    {
        $candidate = (array) data_get($body, 'candidates.0', []);
        if (filled(data_get($body, 'promptFeedback.blockReason')) || $candidate === [] || in_array($candidate['finishReason'] ?? null, self::REFUSED, true)) {
            throw new AiFailed('refused');
        }
        if (($candidate['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw new AiFailed('too_long');
        }
        $text = '';
        foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
            if (is_array($part) && ! ($part['thought'] ?? false)) {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return new AiAnswer(JsonAnswer::object($text), (string) ($body['modelVersion'] ?? $model), self::usage($body));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{input?: int, output?: int, cache_read?: int}
     */
    public static function usage(array $body): array
    {
        $usage = (array) ($body['usageMetadata'] ?? []);
        $cached = (int) ($usage['cachedContentTokenCount'] ?? 0);

        return array_filter([
            'input' => isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] - $cached : null,
            'output' => isset($usage['candidatesTokenCount']) || isset($usage['thoughtsTokenCount'])
                ? (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0) : null,
            'cache_read' => $cached ?: null,
        ], fn ($v) => $v !== null);
    }
}
