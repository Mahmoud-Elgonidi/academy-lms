<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\Faults\AnthropicFaults;
use Modules\Platform\Ai\Faults\GeminiFaults;
use Modules\Platform\Ai\Faults\OllamaFaults;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\SharedKernel\Health\ServiceFault;

/**
 * "Try it" for an AI provider: one tiny request to a model, with the settings
 * in force, asking for a JSON {ok: true} in a few tokens and holding no one's
 * data, so a key the provider refuses, a credit that ran out, a model it does
 * not have, or (for GPT) strict structured outputs it does not take, show
 * before an academy meets them. An answer cut short still counts as reached.
 */
final class AiProbe
{
    private const TOKENS = 16;

    private const SCHEMA = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];

    private const ASK = 'Answer with {"ok": true}.';

    /** Null when the model answered; else why it would not. With no key or address given, the provider's in force. */
    public function probe(AiProvider $provider, string $model, ?string $key = null, ?string $base = null): ?ServiceFault
    {
        $key ??= $provider->key();
        $base = rtrim($base ?? $provider->baseUrl(), '/');
        $http = Http::acceptJson()->timeout($provider === AiProvider::Ollama ? 60 : 20)->connectTimeout(10);

        try {
            $answer = match ($provider) {
                AiProvider::Claude => $http->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                    ->post($base.'/v1/messages', ['model' => $model, 'max_tokens' => self::TOKENS, 'messages' => [['role' => 'user', 'content' => self::ASK]]]),
                AiProvider::OpenAi => $http->withToken($key)->post($base.'/chat/completions', [
                    'model' => $model, 'messages' => [['role' => 'user', 'content' => self::ASK]], 'max_completion_tokens' => self::TOKENS, 'store' => false,
                    'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'probe', 'strict' => true, 'schema' => self::SCHEMA]],
                ]),
                AiProvider::Gemini => $http->withHeaders(['x-goog-api-key' => $key])->post($base.'/models/'.rawurlencode($model).':generateContent', [
                    'contents' => [['role' => 'user', 'parts' => [['text' => self::ASK]]]],
                    'generationConfig' => ['maxOutputTokens' => self::TOKENS, 'responseMimeType' => 'application/json', 'responseJsonSchema' => self::SCHEMA],
                ]),
                AiProvider::Ollama => ($key !== '' ? $http->withToken($key) : $http)->post($base.'/api/chat', [
                    'model' => $model, 'messages' => [['role' => 'user', 'content' => self::ASK]], 'format' => self::SCHEMA, 'stream' => false,
                    'options' => ['num_predict' => self::TOKENS],
                ]),
            };
        } catch (ConnectionException) {
            return ServiceFault::unreachable();
        }
        if ($answer->successful()) {
            return null;
        }

        $fault = match ($provider) {
            AiProvider::Claude => AnthropicFaults::answer($answer->status(), data_get($answer->json(), 'error.type'), (string) data_get($answer->json(), 'error.message', '')),
            AiProvider::OpenAi => OpenAiFaults::of($answer->status(), $answer->json()),
            AiProvider::Gemini => GeminiFaults::of($answer->status(), $answer->json()),
            AiProvider::Ollama => OllamaFaults::of($answer->status(), $answer->json()),
        };

        return $fault instanceof ServiceFault ? $fault : null;
    }
}
