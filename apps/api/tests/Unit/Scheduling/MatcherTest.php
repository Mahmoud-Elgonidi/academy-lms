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

it('carries a window up to midnight into the next day\'s first hours, Sunday into Monday too', function () {
    expect(StudentPreference::windowsFromHours([6 => [22, 23], 7 => [0, 1, 5]]))->toBe([
        ['weekday' => 6, 'start' => 22 * 60, 'end' => 26 * 60],
        ['weekday' => 7, 'start' => 0, 'end' => 2 * 60],
        ['weekday' => 7, 'start' => 5 * 60, 'end' => 6 * 60],
    ])->and(StudentPreference::windowsFromHours([7 => [23], 1 => [0]])[0])->toBe(['weekday' => 7, 'start' => 23 * 60, 'end' => 25 * 60]);
});

/** A Riyadh teacher free every Saturday 22:00 to Sunday 02:00 for $weeks weeks from 3 Oct 2026. */
function nightTeacher(int $weeks = 9): TeacherAvailability
{
    $free = [];
    for ($i = 0; $i < $weeks; $i++) {
        $day = CarbonImmutable::parse('2026-10-03', 'Asia/Riyadh')->addWeeks($i)->toDateString();
        $free[] = [ts($day.' 22:00', 'Asia/Riyadh'), ts($day.' 22:00', 'Asia/Riyadh') + 4 * 3600];
    }
    $shifts = new IntervalSet($free);

    return new TeacherAvailability('layla', 'Asia/Riyadh', $shifts, $shifts);
}

it('lets a lesson that starts late run past midnight; it belongs to the day it starts', function () {
    $matcher = new Matcher(new LocalTimeResolver);
    $riyadh = fn (array $cells) => new StudentPreference('Asia/Riyadh', StudentPreference::windowsFromHours($cells), 1, 45, CarbonImmutable::parse('2026-10-03'), 8, 8);

    $late = collect($matcher->candidates(nightTeacher(), $riyadh([6 => [23], 7 => [0]])))->first(fn ($c) => $c->teacherTime === '23:30');
    expect($late->studentWeekday)->toBe(6)
        ->and($late->teacherWeekday)->toBe(6)
        ->and($late->coverage)->toBe(8)
        ->and($late->studentEnd(45))->toBe('00:15');

    // Saturday 23:00 alone ends at midnight: 23:30 for 45 minutes does not fit in it.
    expect(collect($matcher->candidates(nightTeacher(), $riyadh([6 => [23]])))->contains(fn ($c) => $c->teacherTime === '23:30'))->toBeFalse();

    // Sunday 00:10, typed: a Sunday lesson inside Saturday night's shift.
    $sunday = $matcher->exact(nightTeacher(), $riyadh([]), 7, '00:10');
    expect($sunday->fits())->toBeTrue()
        ->and($sunday->candidate->key())->toBe('7|00:10')
        ->and($sunday->candidate->typed)->toBeTrue();
});

it('checks an exact minute for a teacher: free on every week, as a weekly time on the teacher\'s clock', function () {
    $matcher = new Matcher(new LocalTimeResolver);
    $check = $matcher->exact(cairoTeacher(), londonStudent(), 6, '16:40');

    expect($check->fits())->toBeTrue()
        ->and($check->reasons)->toBe([])
        ->and($check->candidate->teacherTime)->toBe('18:40')
        ->and($check->candidate->studentTime)->toBe('16:40')
        ->and($check->candidate->coverage)->toBe(8)
        ->and($check->candidate->typed)->toBeTrue();
});

it('says why an exact minute does not fit, week by week', function () {
    $matcher = new Matcher(new LocalTimeResolver);
    $busy = [[ts('2026-11-10 18:00', 'Africa/Cairo'), ts('2026-11-10 18:30', 'Africa/Cairo')]];

    // Tuesday 16:10 London is 18:10 Cairo: a lesson on the 10th; and between London's (25th) and Cairo's (29th) clock changes it is 15:10 for the student.
    $tuesday = $matcher->exact(cairoTeacher(busy: $busy), londonStudent(), 2, '16:10');
    expect($tuesday->fits())->toBeFalse()
        ->and($tuesday->coverage())->toBe(6)
        ->and($tuesday->reasons)->toEqual(['clock' => ['2026-10-27'], 'busy' => ['2026-11-10']]);

    // Noon is outside her shifts every week.
    $noon = $matcher->exact(cairoTeacher(), londonStudent(), 6, '12:00');
    expect($noon->candidate)->toBeNull()
        ->and(array_keys($noon->reasons))->toBe(['shifts'])
        ->and($noon->reasons['shifts'])->toHaveCount(8);

    // Weeks already gone.
    $later = $matcher->exact(cairoTeacher(), londonStudent(minCoverage: 1), 6, '16:40', ts('2026-10-20 00:00', 'UTC'));
    expect($later->reasons['past'])->toBe(['2026-10-03', '2026-10-10', '2026-10-17'])
        ->and($later->fits())->toBeTrue();
});

it('tells leave, a holiday, a full day and another coordinator\'s hold apart', function () {
    $hour = fn (string $local) => [ts($local, 'Africa/Cairo'), ts($local, 'Africa/Cairo') + 3600];
    $planned = new IntervalSet([[ts('2026-10-03 17:00', 'Africa/Cairo'), ts('2026-10-03 23:00', 'Africa/Cairo')]]);
    $teacher = new TeacherAvailability('nour', 'Africa/Cairo', $planned, new IntervalSet, $planned,
        off: new IntervalSet([$hour('2026-10-03 17:00')]),
        closed: new IntervalSet([$hour('2026-10-03 18:00')]),
        booked: new IntervalSet([$hour('2026-10-03 19:00')]),
        held: new IntervalSet([$hour('2026-10-03 20:00')]),
        capped: new IntervalSet([$hour('2026-10-03 21:00')]),
    );
    $why = fn (string $local) => $teacher->whyBusy(ts($local, 'Africa/Cairo'), ts($local, 'Africa/Cairo') + 1800);

    expect([$why('2026-10-03 17:00'), $why('2026-10-03 18:00'), $why('2026-10-03 19:00'), $why('2026-10-03 20:00'), $why('2026-10-03 21:00'), $why('2026-10-03 23:00')])
        ->toBe(['time_off', 'holiday', 'busy', 'held', 'cap', 'shifts']);
});
