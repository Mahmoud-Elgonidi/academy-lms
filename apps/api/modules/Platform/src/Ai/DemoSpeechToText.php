<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;

/**
 * The demo's ears (AI_DRIVER=demo, never in production): a recording
 * "says" the words of a text file beside it ({path}.txt), else a fixed
 * sentence for the helper (platform::ai.demo.heard.*), half a second a word.
 */
final class DemoSpeechToText implements SpeechToText
{
    public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
    {
        if ($clip->bytes() === 0) {
            throw new AiFailed('unheard');
        }
        $side = $clip->path.'.txt';
        $text = is_file($side) ? trim((string) file_get_contents($side)) : (string) __('platform::ai.demo.heard.'.$feature->value);
        $words = [];
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $i => $word) {
            $words[] = new HeardWord($word, $i * 0.5, $i * 0.5 + 0.4);
        }
        AiUsage::note($feature, 'demo', 'demo', ['audio_seconds' => (int) ceil(count($words) * 0.5)], 'ok');

        return new Transcript($text, $words, count($words) * 0.5, 'demo');
    }
}
