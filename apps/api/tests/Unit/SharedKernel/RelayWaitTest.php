<?php

use Modules\SharedKernel\Realtime\Console\LiveRelayCommand;

// live:relay sleeps on the database socket until something is due (docs/architecture/03, section 13):
// on a server it runs with no stop time, and must not spin there.

it('sleeps until the next pulse when it runs for good, not zero milliseconds', function () {
    expect(LiveRelayCommand::waitMs(null, 60_000.0, INF))->toBe(60_000)
        ->and(LiveRelayCommand::waitMs(null, 1_234.6, INF))->toBe(1_234);
});

it('wakes for what comes first: folded signals due, the pulse, or the stop', function () {
    expect(LiveRelayCommand::waitMs(250, 60_000.0, INF))->toBe(250)
        ->and(LiveRelayCommand::waitMs(250, 60_000.0, 90.0))->toBe(90)
        ->and(LiveRelayCommand::waitMs(null, -5.0, INF))->toBe(0)
        ->and(LiveRelayCommand::waitMs(null, 60_000.0, -1.0))->toBe(0);
});
