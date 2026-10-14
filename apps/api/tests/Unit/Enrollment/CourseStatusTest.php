<?php

use Carbon\CarbonImmutable;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;

/*
 * A course's status and its days (docs/architecture/02, section 3, "إيقاف
 * الكورس وإنهاؤه"): which keep their weekly times, which make lessons, and
 * which days a pause or an end takes, on the academy's calendar.
 */

function courseWith(EnrollmentStatus $status, ?string $from = null, ?string $until = null, ?string $ends = null): EnrollmentData
{
    $day = fn (?string $d) => $d === null ? null : CarbonImmutable::parse($d);

    return new EnrollmentData('e', 's', 'sub', 't', $status, CarbonImmutable::parse('2026-10-01'), 'a', pausedFrom: $day($from), pausedUntil: $day($until), endsOn: $day($ends));
}

it('keeps a paused course\'s weekly times but makes it no lessons; an ended one has neither', function () {
    expect(array_map(fn (EnrollmentStatus $s) => [$s->value, $s->keepsSlots(), $s->makesLessons()], EnrollmentStatus::cases()))->toBe([
        ['pending_payment', true, true],
        ['active', true, true],
        ['paused', true, false],
        ['ended', false, false],
    ])->and(EnrollmentStatus::making())->toBe(['pending_payment', 'active']);
});

it('knows a pause\'s days, both ends included', function () {
    $course = courseWith(EnrollmentStatus::Active, '2026-10-13', '2026-10-24');

    expect([$course->pausedOn('2026-10-12'), $course->pausedOn('2026-10-13'), $course->pausedOn('2026-10-24'), $course->pausedOn('2026-10-25')])
        ->toBe([false, true, true, false])
        ->and(courseWith(EnrollmentStatus::Active)->pausedOn('2026-10-13'))->toBeFalse();
});

it('is over from its end day on, and an ended course on every day', function () {
    $ending = courseWith(EnrollmentStatus::Active, ends: '2026-11-01');

    expect([$ending->endedBy('2026-10-31'), $ending->endedBy('2026-11-01'), $ending->endedBy('2026-12-01')])->toBe([false, true, true])
        ->and(courseWith(EnrollmentStatus::Ended)->endedBy('2026-01-01'))->toBeTrue()
        ->and(courseWith(EnrollmentStatus::Paused, '2026-10-13', '2026-10-24')->endedBy('2027-01-01'))->toBeFalse();
});
