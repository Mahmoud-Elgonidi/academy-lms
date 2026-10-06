<?php

use Carbon\CarbonImmutable;
use Modules\Communication\Domain\ChannelChoice;
use Modules\Communication\Domain\QuietHours;

it('holds a message sent at night until the morning on the person\'s own clock', function () {
    $hours = QuietHours::parse('22-07');

    // 20:30 UTC is 23:30 in Riyadh: held until 07:00 Riyadh, 04:00 UTC.
    expect($hours->releaseAt(CarbonImmutable::parse('2026-10-06 20:30', 'UTC'), 'Asia/Riyadh')?->format('Y-m-d H:i'))->toBe('2026-10-07 04:00')
        // The same moment is 21:30 in London: not quiet yet.
        ->and($hours->releaseAt(CarbonImmutable::parse('2026-10-06 20:30', 'UTC'), 'Europe/London'))->toBeNull()
        // 02:00 in Cairo is inside the window, released the same morning.
        ->and($hours->releaseAt(CarbonImmutable::parse('2026-10-06 02:00', 'Africa/Cairo'), 'Africa/Cairo')?->setTimezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-10-06 07:00')
        ->and(QuietHours::parse('off')->releaseAt(CarbonImmutable::parse('2026-10-06 02:00', 'UTC'), 'UTC'))->toBeNull();
});

it('narrows the template\'s channels to the person\'s choice', function () {
    expect(ChannelChoice::channels('all', ['push', 'whatsapp', 'email']))->toBe(['push', 'whatsapp', 'email'])
        ->and(ChannelChoice::channels('no_whatsapp', ['push', 'whatsapp', 'email']))->toBe(['push', 'email'])
        ->and(ChannelChoice::channels('app', ['push', 'whatsapp']))->toBe([]);
});
