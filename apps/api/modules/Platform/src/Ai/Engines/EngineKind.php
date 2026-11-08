<?php

namespace Modules\Platform\Ai\Engines;

use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\ClaudeLanguageModel;
use Modules\Platform\Ai\DemoLanguageModel;
use Modules\Platform\Ai\DemoSpeechToText;
use Modules\Platform\Ai\ElevenLabsSpeechToText;
use Modules\Platform\Ai\GeminiLanguageModel;
use Modules\Platform\Ai\OllamaLanguageModel;
use Modules\Platform\Ai\OpenAiLanguageModel;
use Modules\Platform\Ai\WhisperSpeechToText;

/**
 * What an engine speaks: Claude's API (the Anthropic SDK), any server with
 * OpenAI's Chat Completions (OpenAI itself, vLLM, Ollama's /v1, LiteLLM, a
 * server of one's own), Gemini's own API, Ollama's own /api/chat,
 * ElevenLabs' speech to text, any server with OpenAI's
 * /audio/transcriptions (Whisper, faster-whisper-server, a GPU server of
 * one's own), or the demo's fixed answers (never in production).
 */
enum EngineKind: string
{
    case Anthropic = 'anthropic';
    case OpenAiCompatible = 'openai_compatible';
    case Gemini = 'gemini';
    case Ollama = 'ollama';
    case ElevenLabs = 'elevenlabs_stt';
    case WhisperCompatible = 'whisper_compatible';
    case Demo = 'demo';

    public function text(): bool
    {
        return in_array($this, [self::Anthropic, self::OpenAiCompatible, self::Gemini, self::Ollama, self::Demo], true);
    }

    public function speech(): bool
    {
        return in_array($this, [self::ElevenLabs, self::WhisperCompatible, self::Demo], true);
    }

    public function serves(string $kind): bool
    {
        return $kind === 'speech' ? $this->speech() : $this->text();
    }

    /** A key is needed to be asked (a server of one's own may take none). */
    public function needsKey(): bool
    {
        return in_array($this, [self::Anthropic, self::Gemini, self::ElevenLabs], true);
    }

    /** It answers only at an address someone gives. */
    public function needsUrl(): bool
    {
        return in_array($this, [self::OpenAiCompatible, self::Ollama, self::WhisperCompatible], true);
    }

    /** Where it answers when no address is given. */
    public function defaultBase(): string
    {
        return match ($this) {
            self::Anthropic => 'https://api.anthropic.com',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            self::ElevenLabs => 'https://api.elevenlabs.io',
            default => '',
        };
    }

    /** The provider whose model list and probe it shares (null: none to ask). */
    public function provider(): ?AiProvider
    {
        return match ($this) {
            self::Anthropic => AiProvider::Claude,
            self::OpenAiCompatible => AiProvider::OpenAi,
            self::Gemini => AiProvider::Gemini,
            self::Ollama => AiProvider::Ollama,
            default => null,
        };
    }

    /** @return class-string<TextEngine>|null the adapter that asks it for words */
    public function textAdapter(): ?string
    {
        return match ($this) {
            self::Anthropic => ClaudeLanguageModel::class,
            self::OpenAiCompatible => OpenAiLanguageModel::class,
            self::Gemini => GeminiLanguageModel::class,
            self::Ollama => OllamaLanguageModel::class,
            self::Demo => DemoLanguageModel::class,
            default => null,
        };
    }

    /** @return class-string<SpeechEngine>|null the adapter that asks it what a recording said */
    public function speechAdapter(): ?string
    {
        return match ($this) {
            self::ElevenLabs => ElevenLabsSpeechToText::class,
            self::WhisperCompatible => WhisperSpeechToText::class,
            self::Demo => DemoSpeechToText::class,
            default => null,
        };
    }

    public function label(): string
    {
        return __('platform::engines.kinds.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $kinds = array_filter(self::cases(), fn (self $k) => $k !== self::Demo || ! app()->isProduction());

        return collect($kinds)->mapWithKeys(fn (self $k) => [$k->value => $k->label()])->all();
    }
}
