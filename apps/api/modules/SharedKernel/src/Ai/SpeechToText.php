<?php

namespace Modules\SharedKernel\Ai;

/**
 * What was said in a recording, word by word with times. It is never given
 * what the speaker was meant to say: a hint would hide a student's mistakes.
 */
interface SpeechToText
{
    /** @throws AiFailed */
    public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript;
}
