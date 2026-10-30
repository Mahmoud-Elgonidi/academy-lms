<?php

use Carbon\CarbonImmutable;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Queries\FreeTimesQuery;
use Tests\Support\EnrolledStudent;

/*
 * "Available now" on Find a teacher: where the subject's teachers are free
 * across the week on the student's clock, and ready sets of weekly times.
 * The fixture: Tuesdays and Saturdays, Nour (Cairo, 17:00–21:00, a lesson
 * at 18:00 each) and Omar (Riyadh, 18:00–22:00); until the 25th London is
 * two hours behind both. Two weeks from Tuesday the 6th, both weeks free.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->teacher->update(['name' => 'Nour']);
    $this->other->update(['name' => 'Omar']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function freeTimes(string $tz = 'Europe/London', int $perWeek = 1, bool $forMinor = false): array
{
    $pref = new StudentPreference($tz, [], $perWeek, 30, CarbonImmutable::parse('2026-10-06'), 2, 2);

    return app(FreeTimesQuery::class)->run(new TeacherSearch(test()->quran, $pref, forMinor: $forMinor));
}

/** @return array<int, int> hour => teachers free on a weekday */
function counts(array $free, int $weekday): array
{
    return array_map(fn (array $cell) => $cell['count'], $free['cells'][$weekday] ?? []);
}

it('counts the teachers free in each hour of the student\'s clock, with their names', function () {
    $london = freeTimes();

    // Nour 15:00–19:00 London but her 16:00 lesson (16:30 still fits); Omar 16:00–20:00.
    expect(counts($london, 2))->toEqual([15 => 1, 16 => 2, 17 => 2, 18 => 2, 19 => 1])
        ->and($london['cells'][2][15]['names'])->toBe(['Nour'])
        ->and($london['cells'][2][19]['names'])->toBe(['Omar'])
        ->and(counts($london, 6))->toEqual(counts($london, 2))
        ->and($london['cells'][1] ?? [])->toBe([])
        ->and($london['teachers'])->toBe(2);

    // The same teachers seen from Riyadh: two hours later.
    expect(counts(freeTimes('Asia/Riyadh'), 2))->toEqual([17 => 1, 18 => 2, 19 => 2, 20 => 2, 21 => 1]);
});

it('leaves out a teacher on leave or whose day is full', function () {
    // Omar off on Tuesday the 13th: no Tuesday in both weeks.
    $leave = app(RequestTimeOff::class)->handle($this->other->id, '2026-10-13', '2026-10-13', null, null, 'sick', null, $this->other->id);
    app(DecideTimeOff::class)->handle($leave, true, null, $this->admin->id);

    expect(counts(freeTimes(), 2))->toEqual([15 => 1, 16 => 1, 17 => 1, 18 => 1])
        ->and(counts(freeTimes(), 6))->toEqual([15 => 1, 16 => 2, 17 => 2, 18 => 2, 19 => 1]);

    // Nour takes one lesson a day: her Tuesdays and Saturdays are full.
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', maxDailyLessons: 1, subjects: [new TeacherSubjectData($this->quran)]));

    expect(counts(freeTimes(), 2))->toBe([])
        ->and(collect(freeTimes()['cells'][6])->pluck('names')->flatten()->unique()->values()->all())->toBe(['Omar']);
});

it('counts for a child only the teachers cleared to teach children', function () {
    app(PeopleApi::class)->pauseForMinors($this->other->id, 'Concern #3 is being looked into', $this->admin->id);

    expect(freeTimes(forMinor: true)['teachers'])->toBe(1)
        ->and(counts(freeTimes(forMinor: true), 2))->toEqual([15 => 1, 16 => 1, 17 => 1, 18 => 1])
        ->and(freeTimes()['teachers'])->toBe(2);
});

it('suggests sets of as many weekly times as the lessons a week, where the most teachers are free', function () {
    $two = freeTimes(perWeek: 2)['suggestions'];

    expect($two)->not->toBeEmpty()
        ->and(count($two))->toBeLessThanOrEqual(FreeTimesQuery::SUGGESTIONS);
    foreach ($two as $suggestion) {
        expect($suggestion['times'])->toHaveCount(2)
            ->and(collect($suggestion['times'])->pluck('weekday')->sort()->values()->all())->toBe([2, 6])
            ->and($suggestion['count'])->toBeGreaterThanOrEqual(1)
            ->and(['Nour', 'Omar'])->toContain($suggestion['teacher']);
    }
    // Ranked by how many teachers are free at every time of the set.
    expect(collect($two)->pluck('count')->all())->toBe(collect($two)->pluck('count')->sortDesc()->values()->all());

    expect(collect(freeTimes(perWeek: 1)['suggestions'])->every(fn ($s) => count($s['times']) === 1))->toBeTrue()
        // Only two days a week: three lessons a week have no set.
        ->and(freeTimes(perWeek: 3)['suggestions'])->toBe([]);
});

it('works a search out once per request', function () {
    $query = app(FreeTimesQuery::class);
    $search = new TeacherSearch($this->quran, new StudentPreference('Europe/London', [], 1, 30, CarbonImmutable::parse('2026-10-06'), 2, 2));
    $first = $query->run($search);

    User::factory()->withRole(Role::Teacher)->create();     // nothing a cached answer would see

    expect($query->run($search))->toBe($first);
});
