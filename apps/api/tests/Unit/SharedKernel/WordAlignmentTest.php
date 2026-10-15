<?php

use Modules\SharedKernel\Text\WordAlignment;

/*
 * Lining up what was said with the text it was meant to be (a teacher's
 * help with recited homework). Ordinary sentences here, never the Quran.
 */

function words(string $text): array
{
    return explode(' ', $text);
}

/** The states in order, and the extra words with where they came. */
function lined(string $expected, string $heard): array
{
    $out = WordAlignment::align(words($expected), $heard === '' ? [] : words($heard));

    return [array_column($out['words'], 'state'), array_map(fn ($e) => $e['after'].':'.$e['text'], $out['extra']), $out['words']];
}

const SENTENCE = 'ذَهَبَ الوَلَدُ إلى المَدْرَسَةِ صَباحًا مع أخيه الكبير';

it('reads a whole recitation as said, whatever the marks and alef forms', function () {
    [$states, $extra] = lined(SENTENCE, 'ذهب الولد الي المدرسه صباحا مع اخيه الكبير');

    expect($states)->toBe(array_fill(0, 8, 'ok'))->and($extra)->toBe([]);
});

it('marks a word left out, a word said wrong, and what was said instead', function () {
    [$states, $extra, $words] = lined(SENTENCE, 'ذهب الولد الى البيت صباحا اخيه الكبير');

    expect($states)->toBe(['ok', 'ok', 'ok', 'wrong', 'ok', 'missing', 'ok', 'ok'])
        ->and($words[3]['heard'])->toBe('البيت')
        ->and($extra)->toBe([]);
});

it('counts a word said again as extra, after the word it followed', function () {
    [$states, $extra] = lined(SENTENCE, 'ذهب الولد الولد الى المدرسة صباحا مع اخيه الكبير');

    expect($states)->toBe(array_fill(0, 8, 'ok'))->and($extra)->toBe(['2:الولد']);
});

it('leaves the rest missing when the reciter stopped early', function () {
    [$states] = lined(SENTENCE, 'ذهب الولد الى المدرسة');

    expect($states)->toBe(['ok', 'ok', 'ok', 'ok', 'missing', 'missing', 'missing', 'missing']);
    expect(lined(SENTENCE, '')[0])->toBe(array_fill(0, 8, 'missing'));
});

it('does not count the opening and closing said around a passage', function () {
    [$states, $extra] = lined(SENTENCE, 'أعوذ بالله من الشيطان الرجيم بسم الله الرحمان الرحيم ذهب الولد الى المدرسة صباحا مع اخيه الكبير صدق الله العظيم');

    expect($states)->toBe(array_fill(0, 8, 'ok'))->and($extra)->toBe([]);

    // A text that starts with them keeps them as its own words.
    [$states] = lined('بسم الله الرحمن الرحيم ذهب الولد', 'بسم الله الرحمن الرحيم ذهب الولد');
    expect($states)->toBe(array_fill(0, 6, 'ok'));
});

it('takes a long word one letter apart as spelling, a short one as another word', function () {
    [$states] = lined('المستقيمين قال', 'المستقيمن قل');

    expect($states)->toBe(['ok', 'wrong']);
});

it('joins a "و" heard apart to its word, and passes a word of marks alone', function () {
    [$states] = lined('والشمس وضحاها ۛ', 'و الشمس و ضحاها');

    expect($states)->toBe(['ok', 'ok', 'ok']);
});

it('lines up a long passage quickly, and refuses one too long to check', function () {
    // Words that do not repeat in a pattern, as a passage's do not.
    $pool = words('ذهب الولد الى المدرسة صباحا مع اخيه الكبير وعاد في المساء فرحا بما تعلم من معلمه الجديد');
    $letters = mb_str_split('بتثجحخدذرزسشصضطظعغفقكلمنهي');
    $expected = [];
    for ($i = 0, $seed = 7; $i < WordAlignment::MAX_EXPECTED; $i++) {
        $seed = ($seed * 1103515245 + 12345) % 2147483648;
        $expected[] = $pool[$seed % count($pool)].$letters[intdiv($seed, 97) % count($letters)];
    }
    $heard = $expected;
    array_splice($heard, 500, 3);
    array_splice($heard, 900, 0, ['كلمة', 'زيادة']);
    $heard = array_merge($heard, array_slice($expected, 0, 300));

    $started = microtime(true);
    $out = WordAlignment::align($expected, $heard);
    $states = array_count_values(array_column($out['words'], 'state'));

    expect(microtime(true) - $started)->toBeLessThan(10.0)
        ->and($states['missing'] ?? 0)->toBe(3)
        ->and(count($out['extra']))->toBe(302);
    expect(fn () => WordAlignment::align(array_fill(0, WordAlignment::MAX_EXPECTED + 1, 'كلمة'), []))->toThrow(InvalidArgumentException::class);
});
