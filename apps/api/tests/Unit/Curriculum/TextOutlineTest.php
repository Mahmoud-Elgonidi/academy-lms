<?php

use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Outline\TextOutline;

/*
 * A pasted list or a sheet as an outline (Curriculum\Outline\TextOutline):
 * indentation, dashes or numbering mark the depth, measured from the
 * shallowest line; a second-depth line is a unit when lines sit under it,
 * else a lesson; one depth alone is the lessons of one level.
 */

/** @return list<string> each row as "kind/depth title" */
function shape(array $rows): array
{
    return array_map(fn (array $r) => $r['kind'].'/'.$r['depth'].' '.$r['title_ar'].($r['objective'] ? ' | '.$r['objective'] : ''), $rows);
}

it('reads indentation: no space a level, two a unit, four a lesson', function () {
    $text = "المستوى الأول\n  الوحدة الأولى\n    الألف | يعرف الحرف\n    الباء\n  درس حر\n\nالمستوى الثاني\n\tالتاء";

    expect(shape(TextOutline::parse($text, 'x')))->toBe([
        'level/0 المستوى الأول',
        'unit/1 الوحدة الأولى',
        'lesson/2 الألف | يعرف الحرف',
        'lesson/2 الباء',
        'lesson/1 درس حر',
        'level/0 المستوى الثاني',
        'lesson/1 التاء',
    ]);
});

it('reads dashes and bullets, and markdown nested under a bullet', function () {
    expect(shape(TextOutline::parse("Level\n- Unit\n-- One\n-- Two\n- Loose", 'x')))->toBe([
        'level/0 Level', 'unit/1 Unit', 'lesson/2 One', 'lesson/2 Two', 'lesson/1 Loose',
    ])->and(shape(TextOutline::parse("- Level\n  - Unit\n    - One\n  * Two", 'x')))->toBe([
        'level/0 Level', 'unit/1 Unit', 'lesson/2 One', 'lesson/1 Two',
    ]);
});

it('reads numbering in either kind of digits, and keeps a title that only starts with a number', function () {
    expect(shape(TextOutline::parse("1. Level\n1.1 Unit\n1.1.1 One\n1.1.2. Two\n٢. ثاني\n٢.١ درس", 'x')))->toBe([
        'level/0 Level', 'unit/1 Unit', 'lesson/2 One', 'lesson/2 Two', 'level/0 ثاني', 'lesson/1 درس',
    ])->and(shape(TextOutline::parse("Level\n  100 كلمة\n  1-10 الأعداد", 'x')))->toBe([
        'level/0 Level', 'lesson/1 100 كلمة', 'lesson/1 1-10 الأعداد',
    ]);
});

it('makes a list of one depth the lessons of one level, and reads nothing from blank text', function () {
    expect(shape(TextOutline::parse("أ\nب\n\nت", 'المستوى الأول')))->toBe([
        'level/0 المستوى الأول', 'lesson/1 أ', 'lesson/1 ب', 'lesson/1 ت',
    ])->and(TextOutline::parse("  \n\n", 'x'))->toBe([]);
});

it('reads a sheet whose level and unit carry down, skipping its title row', function () {
    $rows = TextOutline::fromSheet([
        ['المستوى', 'الوحدة', 'الدرس', 'الهدف'],
        ['A1', 'Greetings', 'Hello', 'Say hello'],
        ['', '', 'Goodbye', ''],
        ['', 'Family', 'Mother', ''],
        ['A2', '', 'Travel', ''],
        ['A2', '', 'Hotels', ''],
    ], ['Level', 'Unit', 'Lesson', 'المستوى', 'الوحدة', 'الدرس']);

    expect(shape($rows))->toBe([
        'level/0 A1', 'unit/1 Greetings', 'lesson/2 Hello | Say hello', 'lesson/2 Goodbye', 'unit/1 Family', 'lesson/2 Mother',
        'level/0 A2', 'lesson/1 Travel', 'lesson/1 Hotels',
    ]);
});

it('is held to the limits once cleaned', function () {
    $text = implode("\n", array_map(fn ($i) => "L{$i}\n  a{$i}", range(1, 14)));
    $clean = OutlineRules::clean(TextOutline::parse($text, 'x'));

    expect(OutlineRules::counts($clean['rows']))->toBe(['levels' => 12, 'units' => 0, 'lessons' => 12])
        ->and($clean['dropped'])->toBe(4);
});
