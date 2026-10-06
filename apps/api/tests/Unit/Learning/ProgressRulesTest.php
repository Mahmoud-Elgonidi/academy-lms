<?php

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Domain\NodeProgress;
use Modules\Learning\Domain\ProgressCalculator;
use Modules\Learning\Domain\TodaysPlan;

function tree(): CurriculumTree
{
    return new CurriculumTree('v', [
        new CurriculumNode('L1', null, 'level', 1, 'م1', 'L1'),
        new CurriculumNode('U1', 'L1', 'unit', 1, 'و1', 'U1'),
        new CurriculumNode('a', 'U1', 'lesson', 1, 'أ', 'a', weight: 1, estSessions: 2),
        new CurriculumNode('b', 'U1', 'lesson', 2, 'ب', 'b', weight: 2, estSessions: 1),
        new CurriculumNode('L2', null, 'level', 2, 'م2', 'L2'),
        new CurriculumNode('c', 'L2', 'lesson', 1, 'ج', 'c', weight: 1),
    ]);
}

it('masters a lesson once marked mastered, or after two scores of 4 or more', function () {
    $at = CarbonImmutable::parse('2026-10-05 15:00', 'UTC');
    $once = MasteryRule::apply(new NodeProgress('a'), 'mastered', null, 'mastered_once', $at);
    expect($once->isMastered())->toBeTrue()->and($once->nextReviewAt->toDateString())->toBe('2026-10-12');

    $twice = MasteryRule::apply(new NodeProgress('b'), 'mastered', 4, 'score_twice', $at);
    expect($twice->isMastered())->toBeFalse();
    $twice = MasteryRule::apply($twice, 'practiced', 3, 'score_twice', $at->addDays(3));
    expect($twice->isMastered())->toBeFalse();
    $twice = MasteryRule::apply($twice, 'practiced', 5, 'score_twice', $at->addDays(7));
    expect($twice->isMastered())->toBeTrue()->and($twice->timesSeen)->toBe(3);
});

it('spaces reviews out while they go well and brings a weak one back at the next lesson', function () {
    $at = CarbonImmutable::parse('2026-10-05 15:00', 'UTC');
    $p = MasteryRule::apply(new NodeProgress('a'), 'mastered', 5, 'mastered_once', $at);
    $p = MasteryRule::apply($p, 'practiced', 5, 'mastered_once', $at->addDays(7));
    expect($p->reviewIntervalDays)->toBe(14)->and($p->nextReviewAt->toDateString())->toBe('2026-10-26');

    $p = MasteryRule::apply($p, 'needs_review', 2, 'mastered_once', $at->addDays(21));
    expect($p->status)->toBe('needs_review')
        ->and($p->isMastered())->toBeTrue()
        ->and($p->isReviewDue($at->addDays(22)))->toBeTrue()
        ->and($p->reviewIntervalDays)->toBe(7);
});

it('places the student on the first lesson not mastered and works out %, pace and ETA', function () {
    $at = CarbonImmutable::parse('2026-10-05', 'UTC');
    $progress = ['a' => MasteryRule::apply(new NodeProgress('a'), 'mastered', 5, 'mastered_once', $at)];
    $pos = ProgressCalculator::position(tree(), $progress, [0, 1, 0, 0], 2);

    expect($pos->currentNodeId)->toBe('b')
        ->and($pos->percent)->toBe(25)            // 1 of 4
        ->and($pos->pace)->toBe(0.25)             // 1 weight over 4 lessons
        ->and($pos->etaWeeks)->toBe(6.0)          // 3 left ÷ 0.25 ÷ 2 a week
        ->and(ProgressCalculator::percentUnder(tree(), $progress, 'L1'))->toBe(33);
});

it('has no ETA before the first report, and 100% only when everything is mastered', function () {
    $at = CarbonImmutable::parse('2026-10-05', 'UTC');
    expect(ProgressCalculator::position(tree(), [], [], 2)->etaWeeks)->toBeNull();

    $all = [];
    foreach (['a', 'b', 'c'] as $id) {
        $all[$id] = MasteryRule::apply(new NodeProgress($id), 'mastered', 5, 'mastered_once', $at);
    }
    $pos = ProgressCalculator::position(tree(), $all, [4], 2);
    expect($pos->percent)->toBe(100)->and($pos->finished())->toBeTrue()->and($pos->currentNodeId)->toBeNull()->and($pos->etaWeeks)->toBe(0.0);
});

it('plans the next lesson, the one after once it had its sessions, and the reviews that are due', function () {
    $at = CarbonImmutable::parse('2026-10-05', 'UTC');
    expect(array_map(fn ($i) => [$i->nodeId, $i->reason, $i->suggestedOutcome], TodaysPlan::for(tree(), [], $at)))->toBe([['a', 'next', 'introduced']]);

    $seenTwice = MasteryRule::apply(MasteryRule::apply(new NodeProgress('a'), 'introduced', 3, 'mastered_once', $at), 'practiced', 3, 'mastered_once', $at);
    expect(array_map(fn ($i) => $i->nodeId, TodaysPlan::for(tree(), ['a' => $seenTwice], $at)))->toBe(['a', 'b']);

    $mastered = MasteryRule::apply(new NodeProgress('a'), 'mastered', 5, 'mastered_once', $at);
    $plan = TodaysPlan::for(tree(), ['a' => $mastered], $at->addDays(8));
    expect(array_map(fn ($i) => [$i->nodeId, $i->reason], $plan))->toBe([['b', 'next'], ['a', 'review']]);
});
