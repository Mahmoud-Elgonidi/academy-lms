<?php

namespace Modules\Platform\Ai\Engines;

use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Models\Ai\AiEngine;

/**
 * An engine as one request uses it: a saved engine (console › AI engines),
 * or the server's settings as they were before engines (AI_DRIVER, its key
 * and models; ElevenLabs' key). Read when used, never kept between requests.
 * The key lives only in this object for the length of the call.
 */
final readonly class EngineSpec
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public EngineKind $kind,
        public string $baseUrl,
        public string $key,
        public string $modelBest = '',
        public string $modelFast = '',
        public string $modelSpeech = '',
        public ?int $timeout = null,
        public array $options = [],
        public string $service = '',
        public string $provider = '',
    ) {}

    public static function of(AiEngine $engine): self
    {
        $kind = $engine->kindOf();

        return new self(
            id: $engine->id,
            name: $engine->name,
            kind: $kind,
            baseUrl: rtrim(trim((string) ($engine->base_url ?: $kind->defaultBase())), '/'),
            key: trim((string) $engine->api_key),
            modelBest: trim((string) $engine->model_best),
            modelFast: trim((string) $engine->model_fast),
            modelSpeech: trim((string) $engine->model_speech),
            timeout: $engine->timeout_seconds,
            options: (array) ($engine->options ?? []),
            service: $engine->service(),
            provider: $kind->value,
        );
    }

    /** The words as the server's settings had them: the provider in AI_DRIVER (null when off or the demo). */
    public static function legacyText(?AiProvider $provider = null): ?self
    {
        $provider ??= AiProvider::current();
        if ($provider === null) {
            return null;
        }
        $kind = match ($provider) {
            AiProvider::Claude => EngineKind::Anthropic,
            AiProvider::OpenAi => EngineKind::OpenAiCompatible,
            AiProvider::Gemini => EngineKind::Gemini,
            AiProvider::Ollama => EngineKind::Ollama,
        };

        return new self(
            id: null,
            name: $provider->label(),
            kind: $kind,
            baseUrl: $provider->baseUrl(),
            key: $provider->key(),
            modelBest: $provider->model('best'),
            modelFast: $provider->model('fast'),
            service: $provider->service(),
            provider: $provider->service(),
        );
    }

    /** The ears as the server's settings had them: ElevenLabs with the platform's key. */
    public static function legacySpeech(): self
    {
        $config = (array) config('platform.ai.speech');

        return new self(
            id: null,
            name: 'ElevenLabs',
            kind: EngineKind::ElevenLabs,
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://api.elevenlabs.io'), '/'),
            key: trim((string) ($config['key'] ?? '')),
            modelSpeech: (string) ($config['model'] ?? ''),
            timeout: isset($config['timeout']) ? (int) $config['timeout'] : null,
            options: ['zero_retention' => (bool) ($config['zero_retention'] ?? false)],
            service: 'elevenlabs',
            provider: 'elevenlabs',
        );
    }

    public static function demo(): self
    {
        return new self(id: null, name: 'Demo', kind: EngineKind::Demo, baseUrl: '', key: '', service: 'demo', provider: 'demo');
    }

    /** The text model for a tier; the fast one falls back to the best one. */
    public function model(string $tier): string
    {
        return $tier === 'fast' && $this->modelFast !== '' ? $this->modelFast : $this->modelBest;
    }

    /** It has what it takes to be asked for this kind of work. */
    public function ready(string $kind): bool
    {
        if (! $this->kind->serves($kind)) {
            return false;
        }
        if ($this->kind === EngineKind::Demo) {
            return true;
        }
        if ($this->kind->needsKey() && $this->key === '' || $this->kind->needsUrl() && $this->baseUrl === '') {
            return false;
        }

        return $kind === 'speech' ? $this->modelSpeech !== '' : $this->modelBest !== '';
    }

    /** The seconds to wait: the engine's own, else the server's for a person waiting or for the workers. */
    public function seconds(bool $waiting): int
    {
        return $this->timeout ?? (int) config($waiting ? 'platform.ai.timeout' : 'platform.ai.job_timeout', 40);
    }
}
