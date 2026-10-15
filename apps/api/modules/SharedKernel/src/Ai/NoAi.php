<?php

namespace Modules\SharedKernel\Ai;

/** Until Platform binds the helpers: every one is off. */
final class NoAi implements AcademyAi, LanguageModel, SpeechToText
{
    public function on(AiFeature $feature): bool
    {
        return false;
    }

    public function require(AiFeature $feature): void
    {
        throw new AiFailed('off');
    }

    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        throw new AiFailed('not_configured');
    }

    public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
    {
        throw new AiFailed('not_configured');
    }
}
