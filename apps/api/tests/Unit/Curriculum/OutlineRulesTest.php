<?php

use Modules\Curriculum\Domain\Outline\OutlineRules;

/*
 * An outline draft's rules (Curriculum\Domain\OutlineRules, pure): rows cut to
 * their lengths with a title in both languages, a lesson's depth from its
 * place, what has no level or no room dropped with what is under it, unticked
 * rows left out with their branch, and the limits a version may reach.
 */

function oRow(string $kind, int $depth, string $title, array $extra = []): array
{
    return ['kind' => $kind, 'depth' => $depth, 'title_ar' => $title, 'title_en' => ''] + $extra;
}

it('cleans titles and objectives, fills the other language and keeps a passage by its shape', function () {
    $clean = OutlineRules::clean([
        oRow('level', 0, '  Level  one  '),
        oRow('lesson', 2, str_repeat('ا', 250), ['objective' => str_repeat('b', 1200), 'quran' => ['surah' => '112', 'from' => 1, 'to' => 4]]),
        oRow('lesson', 1, 'Bad passage', ['quran' => ['surah' => 115, 'from' => 1, 'to' => 2]]),
        oRow('unit', 1, ''),
        ['kind' => 'objective', 'title_ar' => 'x'],
    ]);

    [$level, $long, $bad] = $clean['rows'];
    expect($level)->toMatchArray(['title_ar' => 'Level one', 'title_en' => 'Level one', 'depth' => 0, 'on' => true, 'key' => 'r0'])
        ->and(mb_strlen($long['title_ar']))->toBe(OutlineRules::TITLE_MAX)
        ->and(mb_strlen($long['objective']))->toBe(OutlineRules::OBJECTIVE_MAX)
        ->and($long['depth'])->toBe(1)                                   // no unit open: straight in the level
        ->and($long['quran'])->toBe(['surah' => 112, 'from' => 1, 'to' => 4])
        ->and($bad['quran'])->toBeNull()
        ->and($clean['dropped'])->toBe(2);
});

it('drops what has no level above it, and what is under a dropped level or unit', function () {
    $rows = [oRow('lesson', 1, 'orphan'), oRow('unit', 1, 'orphan unit'), oRow('lesson', 2, 'under it')];
    foreach (range(1, 13) as $i) {
        $rows[] = oRow('level', 0, "L{$i}");
        $rows[] = oRow('unit', 1, "U{$i}");
        $rows[] = oRow('lesson', 2, "a{$i}");
    }
    $clean = OutlineRules::clean($rows);

    expect(OutlineRules::counts($clean['rows']))->toBe(['levels' => 12, 'units' => 12, 'lessons' => 12])
        ->and($clean['dropped'])->toBe(3 + 3)
        ->and(end($clean['rows'])['title_ar'])->toBe('a12');
});

it('holds lessons to the maximum', function () {
    $rows = [oRow('level', 0, 'L')];
    foreach (range(1, OutlineRules::LESSONS_MAX + 5) as $i) {
        $rows[] = oRow('lesson', 1, "a{$i}");
    }

    expect(OutlineRules::counts(OutlineRules::clean($rows)['rows'])['lessons'])->toBe(OutlineRules::LESSONS_MAX)
        ->and(OutlineRules::refusal($rows))->toBe('lessons')
        ->and(OutlineRules::refusal(array_slice($rows, 0, 11), 12, 0))->toBe('levels')
        ->and(OutlineRules::refusal(array_slice($rows, 0, 11), 0, OutlineRules::LESSONS_MAX - 10))->toBeNull()
        ->and(OutlineRules::refusal([]))->toBe('empty');
});

it('leaves out an unticked row with its branch, and finds each row\'s parent', function () {
    $rows = OutlineRules::clean([
        oRow('level', 0, 'L1'), oRow('unit', 1, 'U1', ['on' => false]), oRow('lesson', 2, 'a'), oRow('lesson', 1, 'b'),
        oRow('level', 0, 'L2', ['on' => false]), oRow('lesson', 1, 'c'),
        oRow('level', 0, 'L3'), oRow('lesson', 1, 'd', ['on' => false]),
    ])['rows'];

    expect(OutlineRules::counting($rows))->toBe([true, false, false, true, false, false, true, false])
        ->and(array_column(OutlineRules::selected($rows), 'title_ar'))->toBe(['L1', 'b', 'L3'])
        ->and(OutlineRules::parents($rows))->toBe([null, 0, 1, 0, null, 4, null, 6])
        ->and(OutlineRules::branch($rows, 0))->toBe([0, 1, 2, 3])
        ->and(OutlineRules::branch($rows, 1))->toBe([1, 2])
        ->and(OutlineRules::branch($rows, 3))->toBe([3]);
});
