<?php

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Availability\Matcher;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Availability\TeacherAvailability;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Time\LocalTimeResolver;

function ts(string $local, string $tz): int
{
    return CarbonImmutable::parse($local, $tz)->getTimestamp();
}

/** A Cairo teacher free every Saturday and Tuesday 17:00–21:00 local for $weeks weeks from 3 Oct 2026, minus $busy. */
function cairoTeacher(int $weeks = 9, array $busy = []): TeacherAvailability
{
    $free = [];
    $day = CarbonImmutable::parse('2026-10-03', 'Africa/Cairo');
    for ($i = 0; $i < $weeks * 7; $i++, $day = $day->addDay()) {
        if (in_array($day->dayOfWeekIso, [6, 2], true)) {
            $free[] = [ts($day->toDateString().' 17:00', 'Africa/Cairo'), ts($day->toDateString().' 21:00', 'Africa/Cairo')];
        }
    }
    $shifts = new IntervalSet($free);

    return new TeacherAvailability('nour', 'Africa/Cairo', $shifts, $shifts->minus(new IntervalSet($busy)));
}

function londonStudent(array $cells = [6 => [14, 15, 16, 17, 18], 2 => [15, 16, 17, 18]], int $minCoverage = 8): StudentPreference
{
    return new StudentPreference('Europe/London', StudentPreference::windowsFromHours($cells), 2, 30, CarbonImmutable::parse('2026-10-03'), 8, $minCoverage);
}

it('merges, subtracts and checks intervals', function () {
    $set = (new IntervalSet([[0, 10], [5, 20], [30, 40]]))->minus(new IntervalSet([[8, 12], [35, 50]]));

    expect($set->intervals)->toBe([[0, 8], [12, 20], [30, 35]])
        ->and($set->contains(12, 20))->toBeTrue()
        ->and($set->contains(7, 13))->toBeFalse()
        ->and($set->totalSeconds())->toBe(21);
});

it('turns grid cells into windows', function () {
    expect(StudentPreference::windowsFromHours([6 => [17, 14, 15, 18]]))->toBe([
        ['weekday' => 6, 'start' => 14 * 60, 'end' => 16 * 60],
        ['weekday' => 6, 'start' => 17 * 60, 'end' => 19 * 60],
    ]);
});

it('shows the teacher times on the student clock, across both countries changing clocks', function () {
    $matcher = new Matcher(new LocalTimeResolver);
    $candidates = $matcher->candidates(cairoTeacher(), londonStudent());

    // Cairo 18:00 is 16:00 in London in October and still 16:00 after both change clocks.
    $sat18 = collect($candidates)->first(fn ($c) => $c->teacherWeekday === 6 && $c->teacherTime === '18:00');
    expect($sat18->studentWeekday)->toBe(6)
        ->and($sat18->studentTime)->toBe('16:00')
        ->and($sat18->coverage)->toBe(8);
});

it('counts the weeks a time is free and names the dates it is not', function () {
    $busy = [[ts('2026-11-10 18:00', 'Africa/Cairo'), ts('2026-11-10 18:45', 'Africa/Cairo')]];
    $matcher = new Matcher(new LocalTimeResolver);

    $all = $matcher->candidates(cairoTeacher(busy: $busy), londonStudent(minCoverage: 7));
    $tue18 = collect($all)->first(fn ($c) => $c->teacherWeekday === 2 && $c->teacherTime === '18:00');

    expect($tue18->coverage)->toBe(7)
        ->and($tue18->conflictDates)->toBe(['2026-11-10']);

    $strict = $matcher->candidates(cairoTeacher(busy: $busy), londonStudent(minCoverage: 8));
    expect(collect($strict)->contains(fn ($c) => $c->key() === '2|18:00'))->toBeFalse();
});

it('suggests the same time on two days with a day off between', function () {
    $matcher = new Matcher(new LocalTimeResolver);
    $pattern = $matcher->suggest($matcher->candidates(cairoTeacher(), londonStudent()), 2);

    expect(array_map(fn ($c) => $c->studentWeekday, $pattern))->toBe([2, 6])
        ->and(array_unique(array_map(fn ($c) => $c->studentTime, $pattern)))->toHaveCount(1);
});

it('finds nothing outside the student\'s windows', function () {
    $matcher = new Matcher(new LocalTimeResolver);

    expect($matcher->candidates(cairoTeacher(), londonStudent([1 => [9, 10]])))->toBe([]);
});

it('compares two weeks into kept, added and removed spans', function () {
    $before = WeeklyShifts::fromArray([['weekday' => 2, 'start' => '16:00', 'end' => '21:00']], 'Africa/Cairo');
    $after = WeeklyShifts::fromArray([['weekday' => 2, 'start' => '18:00', 'end' => '22:00']], 'Africa/Cairo');

    expect($after->compareTo($before)[2])->toBe([
        ['kind' => 'removed', 'start' => '16:00', 'end' => '18:00', 'minutes' => 120],
        ['kind' => 'same', 'start' => '18:00', 'end' => '21:00', 'minutes' => 180],
        ['kind' => 'added', 'start' => '21:00', 'end' => '22:00', 'minutes' => 60],
    ])->and($after->addedAndRemovedMinutes($before))->toBe([60, 120]);
});
