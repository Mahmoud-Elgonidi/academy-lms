<?php

namespace Modules\SharedKernel\Ai;

/**
 * Hears a recitation and says, word by word, how it went against the text it
 * was meant to be (RecitationResult). The recording is only read: never
 * changed, kept elsewhere or deleted. Fails with AiFailed in the reader's
 * words. The default hears with SpeechToText (never told the text) and lines
 * the words up here (SpeechRecitation); an engine that hears letters and
 * rules too can stand in for it.
 */
interface RecitationEngine
{
    public function check(AiFeature $feature, AudioClip $clip, ExpectedText $expected): RecitationResult;
}
