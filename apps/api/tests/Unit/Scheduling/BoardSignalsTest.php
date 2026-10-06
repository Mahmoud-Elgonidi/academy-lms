<?php

use Modules\Scheduling\Live\BoardSignals;

it('folds a burst of changes into one signal per academy, a quarter of a second after the first', function () {
    $signals = new BoardSignals;
    expect($signals->waitMs(0))->toBeNull();

    $signals->changed('a', 0);
    $signals->changed('a', 100);
    $signals->changed('b', 120);

    expect($signals->waitMs(100))->toBe(150)
        ->and($signals->take(249))->toBe([])
        ->and($signals->take(250))->toBe(['a'])
        ->and($signals->take(369))->toBe([])
        ->and($signals->take(370))->toBe(['b'])
        ->and($signals->waitMs(400))->toBeNull();
});

it('sends a busy academy at most one signal a second', function () {
    $signals = new BoardSignals;
    $signals->changed('a', 0);
    expect($signals->take(250))->toBe(['a']);

    $signals->changed('a', 300);                 // right after: waits until a second after the last one
    expect($signals->take(600))->toBe([])
        ->and($signals->waitMs(600))->toBe(650)
        ->and($signals->take(1250))->toBe(['a']);
});
