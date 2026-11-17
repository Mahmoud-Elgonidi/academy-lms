<?php

use Modules\SharedKernel\Text\ArabicText;

/*
 * Arabic as it is compared (a voice note's words, quotations in a model's
 * answer): the same words with or without their marks, in any alef form,
 * read as one; the text itself is never changed where it is kept.
 */

it('drops the marks, the small high signs and tatweel, and keeps the letters', function () {
    expect(ArabicText::normalize('ٱلْمُعَلِّمُ يَشْرَحُ ٱلدَّرْسَ'))->toBe('المعلم يشرح الدرس')
        // A dagger alef goes with the marks, as speech to text writes the plain spelling.
        ->and(ArabicText::normalize('هَٰذَا ۛ كِتَابٌ مُفِيدٌ'))->toBe('هذا كتاب مفيد')
        ->and(ArabicText::normalize('الـــلـه'))->toBe('الله');
});

it('reads the alef forms, ta marbuta, alef maqsura and the hamza seats as one letter each', function () {
    expect(ArabicText::normalize('أحمد إبراهيم آمن ٱهدنا'))->toBe('احمد ابراهيم امن اهدنا')
        ->and(ArabicText::normalize('مدرسة على'))->toBe('مدرسه علي')
        ->and(ArabicText::normalize('مؤمن سائل'))->toBe('مومن سايل')
        ->and(ArabicText::normalize('الآية ١٢٣'))->toBe('الايه 123');
});

it('drops punctuation, joins a lone waw to its word and collapses spaces', function () {
    expect(ArabicText::normalize('قال: «نعم»، ثم ذهب.'))->toBe('قال نعم ثم ذهب')
        ->and(ArabicText::normalize('و الشمس   و ضحاها'))->toBe('والشمس وضحاها')
        ->and(ArabicText::normalize("  \n "))->toBe('');
});

it('splits into the words it compares, the same however the text was written', function () {
    expect(ArabicText::words('الحمدُ للهِ ربِّ العالمين'))->toBe(['الحمد', 'لله', 'رب', 'العالمين'])
        ->and(ArabicText::words('الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ'))->toBe(ArabicText::words('الحمد لله رب العالمين'))
        ->and(ArabicText::words(''))->toBe([]);
});
