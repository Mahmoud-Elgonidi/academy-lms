<?php

namespace Modules\Platform\Ai;

use Modules\SharedKernel\Ai\LanguageModel;

/**
 * Who writes the academies' helpers' words (AI_DRIVER, chosen in Platform
 * settings): Claude, GPT, Gemini or the platform's own Ollama server. Each
 * has its own key (Ollama: its address) and its own best and fast models, so
 * changing provider never sends one provider's model to another; the fast
 * model falls back to the best one. Claude keeps the settings it always had
 * (platform.ai.key, platform.ai.models.*). Read when used, never kept.
 */
enum AiProvider: string
{
    case Claude = 'claude';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case Ollama = 'ollama';

    /** The provider in force; null when the helpers are off or answer from the demo. */
    public static function current(): ?self
    {
        return self::tryFrom((string) config('platform.ai.driver'));
    }

    /** The name it goes by in ai_usage and ServiceHealth. */
    public function service(): string
    {
        return match ($this) {
            self::Claude => 'anthropic',
            default => $this->value,
        };
    }

    public function key(): string
    {
        return trim((string) config($this->path().'.key'));
    }

    public function baseUrl(): string
    {
        return rtrim(trim((string) match ($this) {
            self::Claude => config('platform.ai.base_url') ?: 'https://api.anthropic.com',
            self::Ollama => config('platform.ai.ollama.url'),
            default => config($this->path().'.base_url'),
        }), '/');
    }

    /** The model for a tier (best, fast); the fast one falls back to the best one. */
    public function model(string $tier): string
    {
        $model = trim((string) config($this->path().'.models.'.$tier));

        return $model !== '' ? $model : trim((string) config($this->path().'.models.best'));
    }

    /** It has what it takes to be asked: a key (Ollama: an address; its key is optional). */
    public function hasCredentials(): bool
    {
        return $this === self::Ollama ? $this->baseUrl() !== '' : $this->key() !== '';
    }

    public function configured(): bool
    {
        return $this->hasCredentials() && $this->model('best') !== '';
    }

    /** @return class-string<LanguageModel> */
    public function languageModel(): string
    {
        return match ($this) {
            self::Claude => ClaudeLanguageModel::class,
            self::OpenAi => OpenAiLanguageModel::class,
            self::Gemini => GeminiLanguageModel::class,
            self::Ollama => OllamaLanguageModel::class,
        };
    }

    /** Its name for the academies' screens and the privacy wording. */
    public function label(): string
    {
        return __('platform::services.names.'.$this->service());
    }

    private function path(): string
    {
        return $this === self::Claude ? 'platform.ai' : 'platform.ai.'.$this->value;
    }
}
