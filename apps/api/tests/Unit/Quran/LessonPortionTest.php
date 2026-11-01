<?php

use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Domain\Programs\ProgramShape;

/*
 * Each lesson's portion from the program and where the learner is
 * (docs/architecture/02, section 26), on a made-up mushaf of numbers only:
 * five surahs (7, 10, 6, 4 and 3 verses), five pages (s1; s2 1–5; s2 6–10;
 * s3; s4 and s5), two parts (the second from s3), four quarters of a hizb
 * (from s1, s2, s3 and s4).
 */

function smallMushaf(array $words = []): MushafLayout
{
    return new MushafLayout([1 => 7, 2 => 10, 3 => 6, 4 => 4, 5 => 3], [1, 8, 13, 18, 24], [1, 18], [1, 8, 18, 24], $words);
}

function portionProgram(array $with = []): ProgramShape
{
    return new ProgramShape(...array_merge([
        'track' => 'hifz', 'fromSurah' => 1, 'fromVerse' => 1, 'toSurah' => 5, 'toVerse' => 3, 'direction' => 'forward', 'unit' => 'verses',
        'newAmount' => 3.0, 'nearPortions' => 0, 'farPages' => 0, 'passGrade' => 'good', 'examStops' => 'none',
    ], $with));
}

/** @param  list<VerseRange>  $ranges */
function spans(array $ranges): array
{
    return array_map(fn (VerseRange $r) => "{$r->surah}:{$r->from}-{$r->to}", $ranges);
}

it('teaches forward through the mushaf and backward surah by surah, each surah\'s verses in order', function () {
    $mushaf = smallMushaf();
    expect(LessonPortion::order(portionProgram(['fromSurah' => 2, 'fromVerse' => 9, 'toSurah' => 3, 'toVerse' => 2]), $mushaf))->toBe([16, 17, 18, 19])
        ->and(LessonPortion::order(portionProgram(['fromSurah' => 3, 'fromVerse' => 5, 'toSurah' => 5, 'toVerse' => 2, 'direction' => 'backward']), $mushaf))
        ->toBe([28, 29, 24, 25, 26, 27, 22, 23]);
    expect(fn () => LessonPortion::order(portionProgram(['fromSurah' => 9]), $mushaf))->toThrow(InvalidArgumentException::class);
});

it('makes the new part nearest the amount from where the learner is, never empty', function () {
    $mushaf = smallMushaf();
    $portion = LessonPortion::make(portionProgram(['track' => 'tilawa']), Position::verse(1, 1), $mushaf);
    expect(spans($portion->new))->toBe(['1:1-3'])
        ->and($portion->next)->toEqual(Position::verse(1, 4))
        ->and($portion->newPages)->toBe([1])
        ->and($portion->finished)->toBeFalse()
        ->and($portion->progress)->toBe(0.0);

    // Reading runs on into the next surah.
    expect(spans(LessonPortion::make(portionProgram(['track' => 'tilawa']), Position::verse(1, 6), $mushaf)->new))->toBe(['1:6-7', '2:1-1']);
    // A quarter of a page of seven equal verses is two of them (the second's middle is within it).
    expect(spans(LessonPortion::make(portionProgram(['unit' => 'page', 'newAmount' => 0.25, 'track' => 'tilawa']), Position::verse(1, 1), $mushaf)->new))->toBe(['1:1-2']);
});

it('closes a surah in its own lesson when memorizing, once the portion is half done', function () {
    $mushaf = smallMushaf();
    $four = portionProgram(['newAmount' => 4.0]);
    // Three verses left of surah 1 (at least half of four): the lesson ends with the surah.
    expect(spans(LessonPortion::make($four, Position::verse(1, 5), $mushaf)->new))->toBe(['1:5-7']);
    // One verse left (less than half): the portion goes on into the next surah.
    expect(spans(LessonPortion::make($four, Position::verse(1, 7), $mushaf)->new))->toBe(['1:7-7', '2:1-3']);
    // Reading (tilawa) does not stop at a surah's end.
    expect(spans(LessonPortion::make(portionProgram(['newAmount' => 4.0, 'track' => 'tilawa']), Position::verse(1, 5), $mushaf)->new))->toBe(['1:5-7', '2:1-1']);
});

it('never leaves a surah with a scrap when memorizing: its last verses come now when they weigh less than half', function () {
    $mushaf = smallMushaf();
    // 1:4–6 would leave 1:7 alone (one verse, less than half of three): it comes too.
    $portion = LessonPortion::make(portionProgram(), Position::verse(1, 4), $mushaf);
    expect(spans($portion->new))->toBe(['1:4-7'])->and($portion->next)->toEqual(Position::verse(2, 1));
    // Two verses left (more than half of three) wait for the next lesson.
    expect(spans(LessonPortion::make(portionProgram(), Position::verse(2, 6), $mushaf)->new))->toBe(['2:6-8']);
    // Reading keeps to the amount.
    expect(spans(LessonPortion::make(portionProgram(['track' => 'tilawa']), Position::verse(1, 4), $mushaf)->new))->toBe(['1:4-6']);
});

it('goes backward from the last surah of the scope, Juz Amma style', function () {
    $mushaf = smallMushaf();
    $talqin = portionProgram(['track' => 'talqin', 'fromSurah' => 3, 'toSurah' => 5, 'toVerse' => 3, 'direction' => 'backward', 'newAmount' => 2.0]);
    expect(LessonPortion::start($talqin, $mushaf))->toEqual(Position::verse(5, 1));

    $first = LessonPortion::make($talqin, Position::verse(5, 1), $mushaf);
    expect(spans($first->new))->toBe(['5:1-2'])->and($first->next)->toEqual(Position::verse(5, 3));
    // The surah's last verse alone (half of two), then the surah before it.
    $second = LessonPortion::make($talqin, Position::verse(5, 3), $mushaf);
    expect(spans($second->new))->toBe(['5:3-3'])->and($second->next)->toEqual(Position::verse(4, 1));
    expect(spans(LessonPortion::make($talqin, Position::verse(4, 3), $mushaf)->new))->toBe(['4:3-4'])
        ->and(LessonPortion::make($talqin, Position::verse(4, 3), $mushaf)->next)->toEqual(Position::verse(3, 1));
});

it('measures pages by their words, lines as fifteen to a page, and quarters of a hizb', function () {
    // Without words every verse of a page weighs the same: page 2 has five.
    $mushaf = smallMushaf();
    $page = portionProgram(['unit' => 'page', 'newAmount' => 1.0]);
    expect(spans(LessonPortion::make($page, Position::verse(2, 1), $mushaf)->new))->toBe(['2:1-5']);
    expect(spans(LessonPortion::make(portionProgram(['unit' => 'page', 'newAmount' => 0.5]), Position::verse(2, 1), $mushaf)->new))->toBe(['2:1-3']);

    // Page 4 (surah 3) with words 5, 5, 10, 10, 15, 15: five lines are its first three verses (1.25 + 1.25 + 2.5).
    $words = array_merge(array_fill(0, 17, 10), [5, 5, 10, 10, 15, 15], array_fill(0, 7, 10));
    $lines = portionProgram(['unit' => 'lines', 'newAmount' => 5.0, 'track' => 'tilawa']);
    expect(spans(LessonPortion::make($lines, Position::verse(3, 1), smallMushaf($words))->new))->toBe(['3:1-3'])
        ->and(smallMushaf($words)->weight(18, 'lines'))->toBe(1.25)
        ->and(smallMushaf($words)->pagesIn([VerseRange::of(3, 1, 3)]))->toBe(20 / 60);

    // A quarter from the start of surah 3 is the whole surah (the quarter ends with it).
    $quarter = portionProgram(['unit' => 'quarter_hizb', 'newAmount' => 1.0, 'track' => 'ijaza']);
    expect(spans(LessonPortion::make($quarter, Position::verse(3, 1), $mushaf)->new))->toBe(['3:1-6']);
    expect(fn () => LessonPortion::make($quarter, Position::verse(3, 1), new MushafLayout([1 => 7, 2 => 10, 3 => 6, 4 => 4, 5 => 3], [1, 8, 13, 18, 24], [1, 18])))
        ->toThrow(InvalidArgumentException::class);
});

it('reviews the last portions before where the learner is, in the order taught', function () {
    $mushaf = smallMushaf();
    $near = LessonPortion::make(portionProgram(['newAmount' => 2.0, 'nearPortions' => 2]), Position::verse(2, 3), $mushaf);
    expect(spans($near->near))->toBe(['1:6-7', '2:1-2'])->and($near->nearPages)->toBe([1, 2]);
    // At the very start there is nothing to review yet.
    expect(LessonPortion::make(portionProgram(['nearPortions' => 3]), Position::verse(1, 1), $mushaf)->near)->toBe([]);
    // Backward: what was taught before is the surahs after.
    $backward = portionProgram(['track' => 'talqin', 'fromSurah' => 3, 'direction' => 'backward', 'newAmount' => 2.0, 'nearPortions' => 2]);
    expect(spans(LessonPortion::make($backward, Position::verse(4, 1), $mushaf)->near))->toBe(['5:1-3']);
});

it('cycles the far review over what is memorized, outside the new and near parts', function () {
    $mushaf = smallMushaf();
    $far = portionProgram(['farPages' => 2]);
    $at = Position::verse(3, 1);   // the new part is on page 4
    expect(LessonPortion::make($far, $at, $mushaf, [1, 2, 3, 5])->farPages)->toBe([1, 2])
        ->and(LessonPortion::make($far, $at, $mushaf, [1, 2, 3, 5], 2)->farPages)->toBe([3, 5])
        ->and(LessonPortion::make($far, $at, $mushaf, [1, 2, 3, 5], 5)->farPages)->toBe([1, 2])
        ->and(LessonPortion::make($far, $at, $mushaf, [5, 4], 5)->farPages)->toBe([5])
        ->and(spans(LessonPortion::make($far, $at, $mushaf, [2, 3])->far))->toBe(['2:1-10']);
    // The near review's pages are not reviewed twice.
    expect(LessonPortion::make(portionProgram(['farPages' => 3, 'nearPortions' => 2]), Position::verse(2, 1), $mushaf, [1, 2, 3, 4, 5])->farPages)->toBe([3, 4, 5]);
    // Backward, the far review goes from the mushaf's end.
    $backward = portionProgram(['direction' => 'backward', 'farPages' => 2]);
    expect(LessonPortion::make($backward, Position::verse(1, 1), $mushaf, [2, 3, 5])->farPages)->toBe([5, 3])
        ->and(LessonPortion::make($backward, Position::verse(1, 1), $mushaf, [2, 3, 5], 3)->farPages)->toBe([2, 5]);
    // None memorized, none to review.
    expect(LessonPortion::make($far, $at, $mushaf)->far)->toBe([]);
});

it('ends at the scope\'s end, and is finished past it', function () {
    $mushaf = smallMushaf();
    $scope = portionProgram(['track' => 'tilawa', 'toSurah' => 2, 'toVerse' => 2]);
    $last = LessonPortion::make($scope, Position::verse(2, 1), $mushaf);
    expect(spans($last->new))->toBe(['2:1-2'])->and($last->next)->toBeNull()->and($last->finished)->toBeFalse();
    $done = LessonPortion::make(portionProgram(['nearPortions' => 1]), null, $mushaf);
    expect($done->new)->toBe([])->and($done->finished)->toBeTrue()->and($done->progress)->toBe(1.0)
        ->and(spans($done->near))->toBe(['5:1-3']);
    expect(LessonPortion::make($scope, Position::verse(1, 5), $mushaf)->progress)->toBe(round(4 / 9, 4));
    // A place outside the scope is no position of it.
    expect(fn () => LessonPortion::make($scope, Position::verse(3, 1), $mushaf))->toThrow(InvalidArgumentException::class)
        ->and(LessonPortion::holds($scope, $mushaf, Position::verse(3, 1)))->toBeFalse()
        ->and(LessonPortion::holds($scope, $mushaf, Position::verse(2, 2)))->toBeTrue();
});

it('says which exam stops the new part completes', function () {
    $mushaf = smallMushaf();
    expect(LessonPortion::make(portionProgram(['examStops' => 'surah']), Position::verse(1, 5), $mushaf)->stops)->toBe([['kind' => 'surah', 'number' => 1]])
        ->and(LessonPortion::make(portionProgram(['examStops' => 'surah']), Position::verse(1, 1), $mushaf)->stops)->toBe([])
        ->and(LessonPortion::make(portionProgram(['examStops' => 'juz', 'newAmount' => 5.0, 'track' => 'tilawa']), Position::verse(2, 8), $mushaf)->stops)->toBe([['kind' => 'juz', 'number' => 1]])
        ->and(LessonPortion::make(portionProgram(['examStops' => 'none']), Position::verse(1, 5), $mushaf)->stops)->toBe([]);
    // Backward, a part is done with its first surah (the last one taught).
    $backward = portionProgram(['track' => 'talqin', 'fromSurah' => 3, 'direction' => 'backward', 'newAmount' => 6.0, 'examStops' => 'juz']);
    expect(LessonPortion::make($backward, Position::verse(4, 1), $mushaf)->stops)->toBe([])
        ->and(LessonPortion::make($backward, Position::verse(3, 1), $mushaf)->stops)->toBe([['kind' => 'juz', 'number' => 2]]);
});

it('says which of the new part\'s pages it completes', function () {
    $mushaf = smallMushaf();
    $half = portionProgram(['unit' => 'page', 'newAmount' => 0.5]);
    expect(LessonPortion::make($half, Position::verse(2, 1), $mushaf)->completedPages)->toBe([])
        ->and(LessonPortion::make($half, Position::verse(2, 4), $mushaf)->completedPages)->toBe([2]);
});

it('starts a learner after what they have memorized, on memorizing tracks', function () {
    $mushaf = smallMushaf();
    expect(LessonPortion::start(portionProgram(), $mushaf, [1]))->toEqual(Position::verse(2, 1))
        ->and(LessonPortion::start(portionProgram(), $mushaf, [1, 2, 3, 4, 5]))->toBeNull()
        ->and(LessonPortion::start(portionProgram(['track' => 'tilawa']), $mushaf, [1]))->toEqual(Position::verse(1, 1))
        ->and(LessonPortion::start(portionProgram(['track' => 'talqin', 'fromSurah' => 3, 'direction' => 'backward']), $mushaf, [5]))->toEqual(Position::verse(3, 1));
});

it('reviews only on review and consolidation, and numbers the Noorani Qaida\'s lessons', function () {
    $mushaf = smallMushaf();
    $review = LessonPortion::make(portionProgram(['track' => 'review', 'newAmount' => 0.0, 'farPages' => 2]), null, $mushaf, [3, 4]);
    expect($review->new)->toBe([])->and($review->finished)->toBeFalse()->and($review->farPages)->toBe([3, 4])->and($review->has('new'))->toBeFalse();

    $noorani = portionProgram(['track' => 'noorani', 'fromSurah' => null, 'toSurah' => null, 'fromVerse' => 1, 'toVerse' => 17, 'unit' => 'lesson', 'newAmount' => 1.0]);
    expect(LessonPortion::start($noorani, $mushaf))->toEqual(Position::lesson(1));
    $third = LessonPortion::make($noorani, Position::lesson(3), $mushaf);
    expect($third->lesson)->toBe(3)->and($third->next)->toEqual(Position::lesson(4))->and($third->has('new'))->toBeTrue()->and($third->new)->toBe([]);
    expect(LessonPortion::make($noorani, Position::lesson(17), $mushaf)->next)->toBeNull()
        ->and(LessonPortion::make($noorani, null, $mushaf)->finished)->toBeTrue()
        ->and(LessonPortion::holds($noorani, $mushaf, Position::lesson(18)))->toBeFalse();
});
