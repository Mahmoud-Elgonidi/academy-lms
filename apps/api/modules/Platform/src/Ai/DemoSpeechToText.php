<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\Engines\SpeechEngine;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;

/**
 * The demo's ears (AI_DRIVER=demo, never in production): a recording
 * "says" the words of a text file beside it ({path}.txt), else a fixed
 * sentence for the helper (platform::ai.demo.heard.*, or a capability's own
 * demo ['heard' => …]), half a second a word.
 */
final class DemoSpeechToText implements SpeechEngine, SpeechToText
{
    public function transcribe(AiFeature|string $capability, AudioClip $clip, string $language = 'ara'): Transcript
    {
        return $this->hear(EngineSpec::demo(), Capabilities::key($capability), $clip, $language);
    }

    public function hear(EngineSpec $engine, string $capability, AudioClip $clip, string $language): Transcript
    {
        if ($clip->bytes() === 0) {
            throw new AiFailed('unheard');
        }
        $side = $clip->path.'.txt';
        $fixed = 'platform::ai.demo.heard.'.$capability;
        $text = match (true) {
            is_file($side) => trim((string) file_get_contents($side)),
            trans()->has($fixed) => (string) __($fixed),
            default => (string) (app(Capabilities::class)->get($capability)->demo['heard'] ?? ''),
        };
        $words = [];
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $i => $word) {
            $words[] = new HeardWord($word, $i * 0.5, $i * 0.5 + 0.4);
        }
        AiUsage::note($capability, $engine->provider, 'demo', ['audio_seconds' => (int) ceil(count($words) * 0.5)], 'ok');

        return new Transcript($text, $words, count($words) * 0.5, 'demo');
    }
}
