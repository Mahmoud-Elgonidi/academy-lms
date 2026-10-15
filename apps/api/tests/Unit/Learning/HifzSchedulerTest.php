<?php

use Carbon\CarbonImmutable;
use Modules\Learning\Domain\Hifz\Scheduler;

/*
 * When a memorized page comes back for review: spaced repetition made small
 * (docs/architecture/02, section 22, the hifz map).
 */

function day(int $n): CarbonImmutable
{
    return CarbonImmutable::parse('2027-01-01 18:00', 'UTC')->addDays($n);
}

it('starts a page by how its first review went', function () {
    $good = Scheduler::first(Scheduler::GOOD, day(0));
    expect($good)->toMatchArray(['state' => 'learning', 'stability' => 3.0, 'reviews' => 1, 'lapses' => 0])
        ->and($good['due_at']->equalTo(day(3)))->toBeTrue()
        ->and(Scheduler::first(Scheduler::AGAIN, day(0))['lapses'])->toBe(1)
        ->and(Scheduler::first(Scheduler::EASY, day(0))['state'])->toBe('memorized')
        ->and(Scheduler::first(Scheduler::HARD, day(0), memorized: true)['state'])->toBe('memorized');
});

it('spaces a page further each time it is recited well when due, most when easy', function () {
    $card = Scheduler::first(Scheduler::GOOD, day(0));
    $next = fn (int $grade) => Scheduler::next($card, $grade, $card['due_at']);

    expect($next(Scheduler::GOOD)['stability'])->toBeGreaterThan(6.0)->toBeLessThan(10.0)
        ->and($next(Scheduler::GOOD)['state'])->toBe('memorized')
        ->and($next(Scheduler::EASY)['stability'])->toBeGreaterThan($next(Scheduler::GOOD)['stability'])
        ->and($next(Scheduler::HARD)['stability'])->toBeLessThan($next(Scheduler::GOOD)['stability'])->toBeGreaterThan(3.0);

    // Four good reviews, each when due: weeks, then months.
    foreach (range(1, 4) as $i) {
        $card = Scheduler::next($card, Scheduler::GOOD, $card['due_at']);
    }
    expect($card['stability'])->toBeGreaterThan(40.0)->and($card['reviews'])->toBe(5);
});

it('grows a page little when it is recited again while still fresh', function () {
    $card = Scheduler::marked('good', day(0));
    $soon = Scheduler::next($card, Scheduler::GOOD, day(1));
    $due = Scheduler::next($card, Scheduler::GOOD, $card['due_at']);

    expect($soon['stability'])->toBeGreaterThan(10.0)->toBeLessThan($due['stability']);
});

it('brings a forgotten page back to days, still memorized, with a lapse', function () {
    $card = Scheduler::marked('strong', day(0));
    $forgot = Scheduler::next($card, Scheduler::AGAIN, $card['due_at']);

    expect($forgot)->toMatchArray(['state' => 'memorized', 'lapses' => 1, 'reviews' => 1])
        ->and($forgot['stability'])->toBeLessThan(10.0)
        ->and($forgot['difficulty'])->toBeGreaterThan($card['difficulty']);
});

it('shows a page strong, fading or weak by how likely it is remembered now', function () {
    $card = Scheduler::marked('good', day(0));

    expect(Scheduler::strength(null, day(0)))->toBe('new')
        ->and(Scheduler::strength(Scheduler::first(Scheduler::HARD, day(0)), day(0)))->toBe('learning')
        ->and(Scheduler::strength($card, day(1)))->toBe('strong')
        ->and(Scheduler::strength($card, day(20)))->toBe('fading')
        ->and(Scheduler::strength($card, day(60)))->toBe('weak')
        ->and(Scheduler::recall(10.0, 10.0))->toEqualWithDelta(0.9, 1e-9);
});

it('grades a recitation by the share of words said right, and never waits past half a year', function () {
    expect([Scheduler::gradeFor(1.0), Scheduler::gradeFor(0.9), Scheduler::gradeFor(0.7), Scheduler::gradeFor(0.3)])->toBe([4, 3, 2, 1]);

    $card = Scheduler::marked('strong', day(0));
    foreach (range(1, 8) as $i) {
        $card = Scheduler::next($card, Scheduler::EASY, $card['due_at']);
    }
    expect($card['due_at']->lessThanOrEqualTo($card['last_reviewed_at']->addDays(Scheduler::MAX_DAYS)))->toBeTrue();
    expect(fn () => Scheduler::first(5, day(0)))->toThrow(InvalidArgumentException::class);
});
