<?php

namespace Modules\SharedKernel\Ai;

/**
 * What was said in a recording, word by word with times. It is never given
 * what the speaker was meant to say: a hint would hide a student's mistakes.
 */
interface SpeechToText
{
    /**
     * @param  AiFeature|string  $capability  the helper, or a registered capability's key: the gateway routes by it
     *
     * @throws AiFailed
     */
    public function transcribe(AiFeature|string $capability, AudioClip $clip, string $language = 'ara'): Transcript;
}
