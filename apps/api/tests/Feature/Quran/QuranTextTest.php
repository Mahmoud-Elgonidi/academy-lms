<?php

use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Texts\QuranText;

/*
 * The Quran as the module reads it (docs/architecture/02, section 26): a
 * passage's words come only from the platform's copy, never typed. The
 * Quran here is a fixture in the shape of Tanzil's files (made-up words):
 * surah 1 has three verses (the first is the basmala), surah 2 two (the
 * basmala at the head of its first verse, which is kept apart).
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
});

it('reads a passage from the platform\'s copy alone, the basmala apart', function () {
    $quran = app(QuranApi::class);
    $passage = $quran->passage(VerseRange::of(2, 1, 2));
    expect($passage->title)->toBe('الثانية ١–٢')
        ->and($passage->words)->toBe(['حَرْفٌ', 'نَصٌّ', 'آخَرُ', 'لِلتَّجْرِبَةِ', 'فَقَطْ'])     // the basmala kept apart
        ->and(array_column($passage->verses, 'verse'))->toBe([1, 2])
        ->and($passage->verses[1])->toMatchArray(['first' => 2, 'last' => 5])
        ->and($passage->quote(2, 3))->toBe('نَصٌّ آخَرُ')
        ->and($passage->text())->toBe('حَرْفٌ ﴿١﴾ نَصٌّ آخَرُ لِلتَّجْرِبَةِ فَقَطْ ﴿٢﴾')
        ->and($passage->attribution)->toContain('Tanzil')
        ->and($passage->source->toArray())->toBe(['kind' => 'quran', 'surah' => 2, 'from' => 1, 'to' => 2]);

    // Al-Fatiha's basmala is its first verse; verses not there are no passage.
    expect($quran->passage(VerseRange::of(1, 1, 1))->words)->toBe(['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ'])
        ->and($quran->passage(VerseRange::of(1, 2, 4)))->toBeNull()
        ->and($quran->passage(VerseRange::of(3, 1, 1)))->toBeNull()
        ->and($quran->surahs())->toBe([
            ['number' => 1, 'name_ar' => 'الأولى', 'name_en' => 'Al-Ula', 'verses' => 3],
            ['number' => 2, 'name_ar' => 'الثانية', 'name_en' => 'Ath-Thaniya', 'verses' => 2],
        ]);
    expect(VerseRange::fromArray(['kind' => 'text', 'text' => 'x']))->toBeNull();

    // Until the platform has the Quran's files, there is nothing to read.
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/nowhere')));
    expect(app(QuranApi::class)->surahs())->toBe([])->and(app(QuranApi::class)->available())->toBeFalse();
});

it('knows three words of the Quran in a row wherever they are written, marks and alef forms aside', function () {
    $quran = app(QuranText::class);

    expect($quran->quotes('سؤال: كلمه اولي وثانيه؟'))->toBeTrue()
        ->and($quran->quotes('كَلِمَةٌ أُولَى'))->toBeFalse()
        ->and($quran->quotes('كلمة ثالثة أولى'))->toBeFalse();
});
