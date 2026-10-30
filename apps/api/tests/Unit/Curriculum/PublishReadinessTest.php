<?php

use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Domain\PublishReadiness;
use Modules\Curriculum\Domain\ReadinessItem;

/*
 * Whether a curriculum version may be published (Curriculum\Domain\PublishReadiness):
 * no lesson or an empty level or unit stops it; a lesson without an objective,
 * without practice, or (in a curriculum that reads the Quran) without a text is
 * only a warning. The builder's bar and PublishVersion read the same.
 */

function rTree(array $nodes): CurriculumTree
{
    $out = [];
    foreach ($nodes as $i => [$id, $parent, $kind, $objective]) {
        $out[] = new CurriculumNode($id, $parent, $kind, $i + 1, $id, $id, objectives: $objective);
    }

    return new CurriculumTree('v', $out);
}

/** @return list<string> "severity:key:node" */
function rItems(PublishReadiness $r): array
{
    return array_map(fn (ReadinessItem $i) => $i->severity.':'.$i->key.':'.($i->nodeId ?? '-'), $r->items);
}

it('stops an empty version', function () {
    $r = PublishReadiness::of(rTree([]), [], []);

    expect($r->canPublish())->toBeFalse()
        ->and(rItems($r))->toBe(['blocker:no_lessons:-'])
        ->and($r->percent)->toBe(0);
});

it('stops a version with an empty level or unit, and says which', function () {
    $r = PublishReadiness::of(rTree([
        ['L1', null, 'level', null],
        ['U1', 'L1', 'unit', null],
        ['A', 'U1', 'lesson', 'Read the letters'],
        ['U2', 'L1', 'unit', null],
        ['L2', null, 'level', null],
    ]), ['A' => 3], []);

    expect($r->canPublish())->toBeFalse()
        ->and(rItems($r))->toBe(['blocker:empty_unit:U2', 'blocker:empty_level:L2'])
        ->and($r->percent)->toBeLessThan(100);
});

it('lets a version through with warnings: no objective, no practice', function () {
    $r = PublishReadiness::of(rTree([
        ['L1', null, 'level', null],
        ['A', 'L1', 'lesson', 'Read'],
        ['B', 'L1', 'lesson', '  '],
    ]), ['A' => 4], []);

    expect($r->canPublish())->toBeTrue()
        ->and($r->blockers())->toBe([])
        ->and(rItems($r))->toBe(['warning:no_objective:B', 'warning:no_practice:B'])
        // Five checks (the level holds lessons; two per lesson), three passed.
        ->and($r->percent)->toBe(60);
});

it('asks every lesson of a curriculum that reads the Quran for its text, and no other', function () {
    $tree = rTree([
        ['L1', null, 'level', null],
        ['A', 'L1', 'lesson', 'x'],
        ['B', 'L1', 'lesson', 'x'],
    ]);

    expect(rItems(PublishReadiness::of($tree, ['A' => 1, 'B' => 1], ['A' => 'quran'])))->toBe(['warning:no_text:B'])
        ->and(rItems(PublishReadiness::of($tree, ['A' => 1, 'B' => 1], ['A' => 'text'])))->toBe([])
        ->and(PublishReadiness::of($tree, ['A' => 1, 'B' => 1], ['A' => 'text'])->percent)->toBe(100);
});
