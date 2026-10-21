<?php

namespace Modules\SharedKernel\Ai;

use Modules\SharedKernel\Text\WordAlignment;

/**
 * The default recitation engine: speech to text hears the recording (told
 * only the language, never the text, so it cannot correct the reciter), and
 * WordAlignment lines its words up with the text. Words only: no letters,
 * no rules of recitation.
 */
final class SpeechRecitation implements RecitationEngine
{
    public function __construct(private readonly SpeechToText $speech) {}

    public function check(AiFeature $feature, AudioClip $clip, ExpectedText $expected): RecitationResult
    {
        $words = $expected->words();
        if ($words === [] || count($words) > WordAlignment::MAX_EXPECTED) {
            throw new AiFailed('too_long');
        }
        $heard = $this->speech->transcribe($feature, $clip, 'ara');
        $said = array_map(fn (HeardWord $w) => $w->text, $heard->words) ?: (preg_split('/\s+/u', trim($heard->text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($said === []) {
            throw new AiFailed('unheard');
        }
        $lined = WordAlignment::align($words, $said);

        $verses = [];
        $at = 0;
        foreach ($expected->verses as $verse) {
            $own = [];
            foreach ($verse['words'] as $text) {
                $own[] = ['text' => $text] + $lined['words'][$at++];
            }
            $verses[] = ['surah' => $verse['surah'], 'verse' => $verse['verse'], 'words' => $own];
        }

        return new RecitationResult($verses, $lined['extra'], $heard->text, $heard->seconds, 'words:'.$heard->model);
    }
}
