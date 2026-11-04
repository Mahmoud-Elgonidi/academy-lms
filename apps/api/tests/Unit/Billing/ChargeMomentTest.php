<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Domain\Cards\ChargeMoment;

/*
 * When a renewal is charged to the saved card (J5b): the due moment or three
 * days after the notice on the payer's clock, whichever is later; early enough
 * before a lesson rule 16 would cancel that "pay to keep it" still has its day.
 */

$utc = fn (string $at) => CarbonImmutable::parse($at, 'UTC');

it('waits three days after the notice, or until the due moment when that is later', function () use ($utc) {
    expect(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', $utc('2026-10-07 08:00'))->toIso8601ZuluString())->toBe('2026-10-08T08:00:00Z')
        ->and(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', $utc('2026-10-12 08:00'))->toIso8601ZuluString())->toBe('2026-10-12T08:00:00Z')
        ->and(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', null)->toIso8601ZuluString())->toBe('2026-10-08T08:00:00Z');
});

it('counts the three days on the payer\'s clock, so a clock change keeps the hour they read', function () use ($utc) {
    // Friday 09:00 in London (summer time), the clocks go back on Sunday: Monday 09:00 there is 73 hours later.
    expect(ChargeMoment::of($utc('2026-10-23 08:00'), 'Europe/London', null)->toIso8601ZuluString())->toBe('2026-10-26T09:00:00Z');
});

it('comes early enough before a lesson rule 16 would cancel, never sooner than an hour after the notice', function () use ($utc) {
    // The lesson would be cancelled Wednesday 15:00: charge Tuesday 14:00 (an hour before "pay to keep it" opens).
    expect(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', $utc('2026-10-08 08:00'), $utc('2026-10-07 15:00'))->toIso8601ZuluString())->toBe('2026-10-06T14:00:00Z')
        // Too close already: an hour after the notice.
        ->and(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', $utc('2026-10-08 08:00'), $utc('2026-10-05 20:00'))->toIso8601ZuluString())->toBe('2026-10-05T09:00:00Z')
        // A lesson far ahead changes nothing.
        ->and(ChargeMoment::of($utc('2026-10-05 08:00'), 'Asia/Riyadh', $utc('2026-10-08 08:00'), $utc('2026-11-03 15:00'))->toIso8601ZuluString())->toBe('2026-10-08T08:00:00Z');
});
