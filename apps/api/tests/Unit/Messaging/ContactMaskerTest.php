<?php

use Modules\Messaging\Domain\ContactMasker;

/*
 * Hiding the ways to reach someone outside the platform (docs/architecture/02,
 * section 17): what is hidden, what stays, and what is only flagged.
 */

function masked(string $text): array
{
    $m = (new ContactMasker(['noor.lms.test']))->mask($text);

    return [$m->text, $m->hidden, $m->flags];
}

it('hides phone numbers however they are written', function (string $typed, string $shown) {
    expect(masked($typed))->toBe([$shown, ['phone'], []]);
})->with([
    'plain' => ['كلمني على 01001234567 بكرة', 'كلمني على [•••] بكرة'],
    'arabic digits' => ['رقمي ٠١٠٠١٢٣٤٥٦٧', 'رقمي [•••]'],
    'persian digits' => ['رقمي ۰۱۰۰۱۲۳۴۵۶۷', 'رقمي [•••]'],
    'spaced out' => ['رقمي ٠ ١ ٠ ٠ ١ ٢ ٣ ٤ ٥ ٦ ٧ تمام', 'رقمي [•••] تمام'],
    'international' => ['+20 100 123 4567.', '[•••].'],
    'brackets and dashes' => ['(+966) 55-123-4567', '[•••]'],
    'dotted' => ['0100.123.4567', '[•••]'],
]);

it('keeps dates, times, counting and ordinary numbers', function (string $text) {
    expect(masked($text))->toBe([$text, [], []]);
})->with([
    'a date and a time range' => ['الحصة يوم 2026-10-12 الساعة 18:00 - 19:00'],
    'two dates' => ['الامتحان 12/10/2026 و 15/10/2026'],
    'counting' => ['عد معايا: 1 2 3 4 5 6 7 8 9 10'],
    'prices and verses' => ['سعر 1500 جنيه للشهر و 12 حصة، سورة البقرة آية 255'],
    'abbreviations' => ['e.g. Mr.Ahmed read 3.5 pages.'],
]);

it('hides emails, written plainly or spelled out', function (string $typed) {
    expect(masked($typed)[1])->toBe(['email'])
        ->and(masked($typed)[0])->not->toContain('gmail');
})->with([
    'plain' => ['ابعتلي على ahmed.ali@gmail.com لو سمحت'],
    'brackets' => ['mail: ahmed (at) gmail (dot) com'],
    'words' => ['ahmed at gmail dot com'],
]);

it('hides links, but not the academy\'s own', function () {
    [$text, $hidden, $flags] = masked('شوف https://youtube.com/watch?v=abc و www.example.org/x، وكلمني wa.me/201001234567');
    expect($text)->toBe('شوف [•••] و [•••]، وكلمني [•••]')
        ->and($hidden)->toBe(['link'])
        ->and($flags)->toBe(['outside_app']);

    $own = 'الواجب هنا https://noor.lms.test/my/homework و noor.lms.test/x.';
    expect(masked($own))->toBe([$own, [], []]);
});

it('hides handles and flags outside apps without hiding them', function () {
    expect(masked('زودني على انستا @ahmed_teacher'))->toBe(['زودني على انستا [•••]', ['handle'], ['outside_app']])
        ->and(masked('كلمني عالواتس'))->toBe(['كلمني عالواتس', [], ['outside_app']])
        ->and(masked('see you at 5 dot com'))->toBe(['see you at 5 dot com', [], []]);
});

it('lists each kind hidden once', function () {
    expect(masked('01001234567 و a@b.co و 01112223334 و https://x.io')[1])->toBe(['email', 'link', 'phone']);
});
