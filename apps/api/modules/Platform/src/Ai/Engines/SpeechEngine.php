<?php

namespace Modules\Platform\Ai\Engines;

use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Transcript;

/**
 * An adapter that asks one engine what a recording said (ElevenLabs, a
 * Whisper-compatible server, the demo), told only the language. Only the
 * gateway and the console's comparison call it; modules ask SpeechToText
 * with their capability's key.
 */
interface SpeechEngine
{
    /** @throws AiFailed */
    public function hear(EngineSpec $engine, string $capability, AudioClip $clip, string $language): Transcript;
}
