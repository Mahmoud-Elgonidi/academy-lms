<?php

use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\ExpectedText;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\RecitationEngine;
use Modules\SharedKernel\Ai\SpeechRecitation;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;

/*
 * The default recitation engine: speech to text hears the recording (never
 * told the text) and the words are lined up here. Ordinary sentences, never
 * the Quran.
 */

it('is the recitation engine until another is bound', function () {
    expect(app(RecitationEngine::class))->toBeInstanceOf(SpeechRecitation::class);
});

it('hears with speech to text, never telling it the text, and gives each verse its words', function () {
    $ears = new class implements SpeechToText
    {
        public ?string $language = null;

        public int $calls = 0;

        public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
        {
            $this->calls++;
            $this->language = $language;

            return new Transcript('ذهب الولد الى البيت صباحا', array_map(fn ($w) => new HeardWord($w, 0, 0), explode(' ', 'ذهب الولد الى البيت صباحا')), 3.5, 'ears-1');
        }
    };
    $expected = new ExpectedText([
        ['surah' => 1, 'verse' => 1, 'words' => explode(' ', 'ذهب الولد')],
        ['surah' => 1, 'verse' => 2, 'words' => explode(' ', 'الى المدرسة صباحا مع')],
    ]);

    $result = (new SpeechRecitation($ears))->check(AiFeature::RecitationCheck, new AudioClip('/nowhere.wav', 'audio/wav'), $expected);

    expect($ears->language)->toBe('ara')
        ->and($result->engine)->toBe('words:ears-1')
        ->and($result->seconds)->toBe(3.5)
        ->and(array_column($result->verses[1]['words'], 'state'))->toBe(['ok', 'wrong', 'ok', 'missing'])
        ->and($result->verses[1]['words'][1])->toBe(['text' => 'المدرسة', 'state' => 'wrong', 'heard' => 'البيت'])
        ->and($result->summary())->toBe(['words' => 6, 'ok' => 4, 'missing' => 1, 'wrong' => 1, 'extra' => 0]);

    expect(fn () => (new SpeechRecitation($ears))->check(AiFeature::RecitationCheck, new AudioClip('/nowhere.wav', 'audio/wav'), new ExpectedText([])))
        ->toThrow(AiFailed::class);
});
