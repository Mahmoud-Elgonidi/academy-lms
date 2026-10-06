<?php

use Modules\SharedKernel\Realtime\SignalFolder;

it('folds a burst of changes into one signal per key, a quarter of a second after the first', function () {
    $signals = new SignalFolder;
    expect($signals->waitMs(0))->toBeNull();

    $signals->changed('board:a', 0);
    $signals->changed('board:a', 100);
    $signals->changed('inbox:u', 120);

    expect($signals->waitMs(100))->toBe(150)
        ->and($signals->take(249))->toBe([])
        ->and($signals->take(250))->toBe(['board:a'])
        ->and($signals->take(369))->toBe([])
        ->and($signals->take(370))->toBe(['inbox:u'])
        ->and($signals->waitMs(400))->toBeNull();
});

it('sends a busy key at most one signal a second', function () {
    $signals = new SignalFolder;
    $signals->changed('board:a', 0);
    expect($signals->take(250))->toBe(['board:a']);

    $signals->changed('board:a', 300);           // right after: waits until a second after the last one
    expect($signals->take(600))->toBe([])
        ->and($signals->waitMs(600))->toBe(650)
        ->and($signals->take(1250))->toBe(['board:a']);
});
