<?php

namespace Modules\Platform\Ai\Gateway;

use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;

/** SharedKernel's SpeechToText as every module sees it: the gateway, by the capability's key. */
final class GatewaySpeechToText implements SpeechToText
{
    public function __construct(private readonly Gateway $gateway) {}

    public function transcribe(AiFeature|string $capability, AudioClip $clip, string $language = 'ara'): Transcript
    {
        return $this->gateway->speech(Capabilities::key($capability), $clip, $language);
    }
}
