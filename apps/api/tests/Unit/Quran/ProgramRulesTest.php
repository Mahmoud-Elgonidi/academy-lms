<?php

use Modules\Quran\Domain\Programs\Presets;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Domain\Programs\ProgramShape;

/*
 * What a Quran program may be (docs/architecture/02, section 26), and the
 * ready tracks it starts from. The surahs here are numbers only: 114 of
 * them with 7 verses each (the 114th 6).
 */

function surahVerses(): array
{
    return array_fill_keys(range(1, 113), 7) + [114 => 6];
}

function hifzProgram(array $with = []): array
{
    return array_merge(['name' => 'Juz Amma', 'track' => 'hifz', 'scope_from_surah' => 78, 'scope_from_verse' => 1, 'scope_to_surah' => 114, 'scope_to_verse' => 6,
        'direction' => 'backward', 'unit' => 'verses', 'new_amount' => 4, 'near_portions' => 2, 'far_pages' => 1, 'pass_grade' => 'good', 'exam_stops' => 'surah'], $with);
}

it('takes every ready track as it is', function () {
    foreach (Presets::keys() as $key) {
        expect(ProgramRules::errors(['name' => $key] + Presets::get($key), surahVerses(), Presets::NOORANI_LESSONS))->toBe([], $key);
    }
    expect(Presets::keys())->toBe(['juz_amma_talqin', 'full_backward', 'full_forward', 'tilawa', 'noorani', 'review', 'ijaza'])
        ->and(Presets::get('juz_amma_talqin'))->toMatchArray(['track' => 'talqin', 'direction' => 'backward', 'unit' => 'verses', 'scope_from_surah' => 78])
        ->and(Presets::get('full_backward'))->toMatchArray(['direction' => 'backward', 'unit' => 'page', 'new_amount' => 0.5])
        ->and(Presets::get('full_forward')['direction'])->toBe('forward');
    expect(fn () => Presets::get('nothing'))->toThrow(InvalidArgumentException::class);
});

it('names what is wrong with a program', function () {
    $errors = fn (array $with) => ProgramRules::errors(hifzProgram($with), surahVerses(), Presets::NOORANI_LESSONS);
    expect($errors([]))->toBe([])
        ->and($errors(['name' => ' ']))->toBe(['name' => 'name'])
        ->and($errors(['track' => 'poetry']))->toBe(['track' => 'track'])
        ->and($errors(['unit' => 'lesson']))->toBe(['unit' => 'unit'])
        ->and($errors(['direction' => 'sideways']))->toBe(['direction' => 'direction'])
        ->and($errors(['scope_from_surah' => 115]))->toBe(['scope_from_surah' => 'verse'])
        ->and($errors(['scope_to_verse' => 7]))->toBe(['scope_to_surah' => 'verse'])
        ->and($errors(['scope_from_surah' => 114, 'scope_to_surah' => 78]))->toBe(['scope_to_surah' => 'order'])
        ->and($errors(['new_amount' => 0]))->toBe(['new_amount' => 'amount'])
        ->and($errors(['new_amount' => 2.5]))->toBe(['new_amount' => 'amount'])
        ->and($errors(['new_amount' => 51]))->toBe(['new_amount' => 'amount'])
        ->and($errors(['unit' => 'page', 'new_amount' => 0.75]))->toBe([])
        ->and($errors(['unit' => 'page', 'new_amount' => 0.6]))->toBe(['new_amount' => 'amount'])
        ->and($errors(['unit' => 'quarter_hizb', 'new_amount' => 1.5]))->toBe([])
        ->and($errors(['unit' => 'lines', 'new_amount' => 61]))->toBe(['new_amount' => 'amount'])
        ->and($errors(['near_portions' => 11]))->toBe(['near_portions' => 'near'])
        ->and($errors(['far_pages' => 41]))->toBe(['far_pages' => 'far'])
        ->and($errors(['pass_grade' => 'repeat']))->toBe(['pass_grade' => 'pass_grade'])
        ->and($errors(['exam_stops' => 'hizb']))->toBe(['exam_stops' => 'exam_stops']);
});

it('holds each track to what it is', function () {
    $errors = fn (array $with) => ProgramRules::errors(hifzProgram($with), surahVerses(), Presets::NOORANI_LESSONS);
    // Review brings nothing new, has no near review or exams, and reviews at least a page.
    expect($errors(['track' => 'review', 'new_amount' => 1, 'near_portions' => 0, 'far_pages' => 0, 'exam_stops' => 'juz']))
        ->toBe(['new_amount' => 'review_new', 'far_pages' => 'far', 'exam_stops' => 'exam_stops']);
    // The Noorani Qaida: its lessons, one a lesson, no reviews or exams.
    $noorani = ['track' => 'noorani', 'unit' => 'lesson', 'scope_from_surah' => null, 'scope_to_surah' => null, 'scope_from_verse' => 1, 'scope_to_verse' => 17,
        'direction' => 'forward', 'new_amount' => 1, 'near_portions' => 0, 'far_pages' => 0, 'exam_stops' => 'none'];
    expect($errors($noorani))->toBe([])
        ->and($errors(['scope_to_verse' => 18] + $noorani))->toBe(['scope_from_verse' => 'lessons'])
        ->and($errors(['new_amount' => 2, 'far_pages' => 1] + $noorani))->toBe(['new_amount' => 'amount', 'far_pages' => 'far'])
        ->and($errors(['unit' => 'page', 'direction' => 'backward'] + $noorani))->toBe(['unit' => 'unit', 'direction' => 'direction']);
});

it('keeps a program as rules say, and a learner\'s pace on it', function () {
    $kept = ProgramRules::normalize(hifzProgram(['name' => '  Juz Amma  ', 'new_amount' => '4', 'counts_for_certificate' => 1]));
    expect($kept)->toMatchArray(['name' => 'Juz Amma', 'new_amount' => 4.0, 'counts_for_certificate' => true, 'scope_from_surah' => 78]);
    $shape = ProgramShape::fromArray($kept)->withPace(2.0, null, 3);
    expect([$shape->newAmount, $shape->nearPortions, $shape->farPages, $shape->direction])->toBe([2.0, 2, 3, 'backward'])
        ->and($shape->stopsAtSurahEnd())->toBeTrue()->and($shape->memorizes())->toBeTrue();
    expect(ProgramRules::paceErrors('hifz', 'page', ['new_amount' => 0.5, 'near_portions' => 3]))->toBe([])
        ->and(ProgramRules::paceErrors('hifz', 'page', ['new_amount' => 0.3]))->toBe(['new_amount' => 'amount'])
        ->and(ProgramRules::paceErrors('review', 'page', ['far_pages' => 0]))->toBe(['far_pages' => 'far'])
        ->and(ProgramRules::paceErrors('hifz', 'verses', ['near_portions' => 12]))->toBe(['near_portions' => 'near']);
});
