<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\Faults\AnthropicFaults;
use Modules\Platform\Ai\Faults\GeminiFaults;
use Modules\Platform\Ai\Faults\OllamaFaults;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;
use Throwable;

/**
 * The models a provider offers this key now, for Platform settings to choose
 * from (no typing, but for "another name…"): Anthropic's and Google's lists,
 * OpenAI's models that write (not the ones that draw, speak, hear or embed),
 * and what the Ollama server has pulled. Asked from the server with the key
 * typed on the screen or the one in force, kept ten minutes (a failure, with
 * why, one minute) under a name made from the address and the key by
 * APP_KEY, never the key itself.
 */
final class ModelCatalog
{
    /** "Another name…": no model's id can start with "_". */
    public const CUSTOM = '__custom__';

    private const TTL = 600;

    private const FAILED_TTL = 60;

    private const TIMEOUT = 8;

    /** Not a model that writes: OpenAI's list holds every kind. */
    private const NOT_WRITING = ['embedding', 'tts', 'whisper', 'dall-e', 'davinci', 'babbage', 'moderation', 'realtime', 'audio',
        'transcribe', 'image', 'search', 'computer-use', 'codex', 'instruct', '-pro', 'deep-research', 'sora'];

    private const GEMINI_NOT_WRITING = ['embedding', 'aqa', 'imagen', 'veo', 'tts', 'image', 'native-audio', 'live', 'robotics', 'computer-use'];

    /**
     * Claude's models as Anthropic names them (October 2026), shown before a key is
     * put in or while Anthropic's own list cannot be had; its list replaces them once
     * the key works. OpenAI and Google list models only for a key, and Ollama only
     * what its server has, so theirs come with the key or the address.
     */
    private const KNOWN = [
        'claude' => [
            'claude-fable-5-1' => 'Claude Fable 5.1',
            'claude-opus-5-5' => 'Claude Opus 5.5',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
            'claude-haiku-5-5' => 'Claude Haiku 5.5',
            'claude-fable-5' => 'Claude Fable 5',
            'claude-opus-5' => 'Claude Opus 5',
            'claude-sonnet-5' => 'Claude Sonnet 5',
            'claude-opus-4-8' => 'Claude Opus 4.8',
            'claude-opus-4-7' => 'Claude Opus 4.7',
            'claude-opus-4-6' => 'Claude Opus 4.6',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
            'claude-haiku-4-5' => 'Claude Haiku 4.5',
        ],
    ];

    /** @return array<string, string> a provider's models known here, as its list shows them (none when it lists its own only for a key) */
    public static function known(AiProvider $provider): array
    {
        return collect(self::KNOWN[$provider->value] ?? [])->mapWithKeys(fn (string $name, string $id) => [$id => "{$name} ({$id})"])->all();
    }

    /** What is kept: the list, or why there is none (with no key or address, that it cannot be asked). */
    public function cached(AiProvider $provider, ?string $key = null, ?string $base = null): ?ModelList
    {
        [$key, $base] = $this->credentials($provider, $key, $base);
        if ($provider === AiProvider::Ollama ? $base === '' : $key === '') {
            return new ModelList([], ServiceProblem::Config, 'missing', now()->getTimestamp());
        }
        try {
            $kept = Cache::get($this->cacheKey($provider, $key, $base));
        } catch (Throwable) {
            return null;
        }

        return is_array($kept) ? ModelList::from($kept) : null;
    }

    /** The list, asking the provider when nothing is kept. */
    public function list(AiProvider $provider, ?string $key = null, ?string $base = null): ModelList
    {
        [$key, $base] = $this->credentials($provider, $key, $base);
        if (($kept = $this->cached($provider, $key, $base)) !== null) {
            return $kept;
        }

        $list = $this->ask($provider, $key, $base);
        try {
            Cache::put($this->cacheKey($provider, $key, $base), $list->toArray(), $list->problem === null ? self::TTL : self::FAILED_TTL);
        } catch (Throwable) {
            // Not kept: asked again next time.
        }

        return $list;
    }

    public function forget(AiProvider $provider, ?string $key = null, ?string $base = null): void
    {
        [$key, $base] = $this->credentials($provider, $key, $base);
        try {
            Cache::forget($this->cacheKey($provider, $key, $base));
        } catch (Throwable) {
            // Nothing kept.
        }
    }

    public function cacheKey(AiProvider $provider, string $key, string $base): string
    {
        return 'ai:models:'.$provider->value.':'.substr(hash_hmac('sha256', $base."\n".$key, (string) config('app.key')), 0, 32);
    }

    private function ask(AiProvider $provider, string $key, string $base): ModelList
    {
        try {
            $options = match ($provider) {
                AiProvider::Claude => $this->anthropic($key, $base),
                AiProvider::OpenAi => $this->openai($key, $base),
                AiProvider::Gemini => $this->gemini($key, $base),
                AiProvider::Ollama => $this->ollama($key, $base),
            };
        } catch (ConnectionException) {
            return new ModelList([], ServiceProblem::Unreachable, 'connection', now()->getTimestamp());
        } catch (ModelListRefused $e) {
            return new ModelList([], $e->fault->problem, $e->fault->code, now()->getTimestamp());
        } catch (Throwable $e) {
            // A list that cannot be had never stops the page: "another name…" still works.
            return new ModelList([], ServiceProblem::Unknown, class_basename($e), now()->getTimestamp());
        }

        return new ModelList($options, null, '', now()->getTimestamp());
    }

    /** @return array<string, string> */
    private function anthropic(string $key, string $base): array
    {
        $options = [];
        $after = null;
        do {
            $answer = $this->got(Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->acceptJson()->timeout(self::TIMEOUT)
                ->get($base.'/v1/models', array_filter(['limit' => 1000, 'after_id' => $after])), AiProvider::Claude);
            foreach ((array) $answer->json('data', []) as $model) {
                $id = (string) ($model['id'] ?? '');
                if ($id !== '') {
                    $name = (string) ($model['display_name'] ?? '');
                    $options[$id] = $name !== '' && $name !== $id ? "{$name} ({$id})" : $id;
                }
            }
            $after = $answer->json('has_more') ? $answer->json('last_id') : null;
        } while ($after !== null && count($options) < 2000);

        return $options;
    }

    /** @return array<string, string> newest first */
    private function openai(string $key, string $base): array
    {
        $answer = $this->got(Http::withToken($key)->acceptJson()->timeout(self::TIMEOUT)->get($base.'/models'), AiProvider::OpenAi);
        $models = array_filter((array) $answer->json('data', []), function ($model) {
            $id = strtolower((string) ($model['id'] ?? ''));

            return preg_match('/^(gpt-|o\d|chatgpt-|ft:)/', $id) === 1 && ! array_filter(self::NOT_WRITING, fn (string $not) => str_contains($id, $not));
        });
        usort($models, fn ($a, $b) => (int) ($b['created'] ?? 0) <=> (int) ($a['created'] ?? 0));

        return collect($models)->mapWithKeys(fn ($m) => [(string) $m['id'] => (string) $m['id']])->all();
    }

    /** @return array<string, string> */
    private function gemini(string $key, string $base): array
    {
        $options = [];
        $page = null;
        do {
            $answer = $this->got(Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout(self::TIMEOUT)
                ->get($base.'/models', array_filter(['pageSize' => 1000, 'pageToken' => $page])), AiProvider::Gemini);
            foreach ((array) $answer->json('models', []) as $model) {
                $id = preg_replace('#^models/#', '', (string) ($model['name'] ?? ''));
                $writes = in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true);
                if ($id === '' || ! $writes || array_filter(self::GEMINI_NOT_WRITING, fn (string $not) => str_contains(strtolower($id), $not))) {
                    continue;
                }
                $name = (string) ($model['displayName'] ?? '');
                $options[$id] = $name !== '' ? "{$name} ({$id})" : $id;
            }
            $page = $answer->json('nextPageToken') ?: null;
        } while ($page !== null && count($options) < 2000);

        return $options;
    }

    /** @return array<string, string> */
    private function ollama(string $key, string $base): array
    {
        $http = Http::acceptJson()->timeout(self::TIMEOUT);
        $answer = $this->got(($key !== '' ? $http->withToken($key) : $http)->get($base.'/api/tags'), AiProvider::Ollama);
        $options = [];
        foreach ((array) $answer->json('models', []) as $model) {
            $id = (string) ($model['name'] ?? $model['model'] ?? '');
            if ($id === '' || str_contains(strtolower($id), 'embed')) {
                continue;
            }
            $about = trim(implode(' ', array_filter([(string) data_get($model, 'details.parameter_size', ''), (string) data_get($model, 'details.quantization_level', '')])));
            $options[$id] = $about !== '' ? "{$id} · {$about}" : $id;
        }

        return $options;
    }

    private function got(Response $answer, AiProvider $provider): Response
    {
        if ($answer->successful()) {
            return $answer;
        }
        $fault = match ($provider) {
            AiProvider::Claude => AnthropicFaults::answer($answer->status(), data_get($answer->json(), 'error.type'), (string) data_get($answer->json(), 'error.message', '')),
            AiProvider::OpenAi => OpenAiFaults::of($answer->status(), $answer->json()),
            AiProvider::Gemini => GeminiFaults::of($answer->status(), $answer->json()),
            AiProvider::Ollama => OllamaFaults::of($answer->status(), $answer->json()),
        };

        throw new ModelListRefused($fault instanceof ServiceFault ? $fault : ServiceFault::http($answer->status()));
    }

    /** @return array{0: string, 1: string} the key and address to ask with: what was typed, else what is in force */
    private function credentials(AiProvider $provider, ?string $key, ?string $base): array
    {
        $key = trim((string) $key) !== '' ? trim((string) $key) : $provider->key();
        $base = trim((string) $base) !== '' ? rtrim(trim((string) $base), '/') : $provider->baseUrl();

        return [$key, $base];
    }
}
