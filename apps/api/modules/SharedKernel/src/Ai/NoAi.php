<?php

namespace Modules\SharedKernel\Ai;

/** Until Platform binds the helpers: every one is off. */
final class NoAi implements AcademyAi, LanguageModel, SpeechToText
{
    public function on(AiFeature|string $capability): bool
    {
        return false;
    }

    public function require(AiFeature|string $capability): void
    {
        throw new AiFailed('off');
    }

    public function json(AiFeature|string $capability, AiPrompt $prompt): AiAnswer
    {
        throw new AiFailed('not_configured');
    }

    public function transcribe(AiFeature|string $capability, AudioClip $clip, string $language = 'ara'): Transcript
    {
        throw new AiFailed('not_configured');
    }
}
