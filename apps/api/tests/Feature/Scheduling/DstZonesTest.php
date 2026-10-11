<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Dst\DecideDstChange;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Domain\Models\ShiftSchedule;

/*
 * The DST planner across the zones academies teach in, on tzdata's real
 * dates (docs/architecture/02, section 1): Morocco goes back to UTC+0 for
 * Ramadan (Sunday 7 February 2027, 03:00 → 02:00) and forward again
 * (Sunday 14 March 2027, 02:00 → 03:00), the same day New York goes
 * forward; New York goes back on 1 November 2026, two days after Cairo;
 * Dubai and Riyadh never change. Weekly times stay on the teacher's clock.
 */

afterEach(fn () => CarbonImmutable::setTestNow());

/** A course booked at $now: weekly at $time on the teacher's clock, from each first local date, for eight weeks; the teacher on shift two hours either side. */
function zonedCourse(string $teacherTz, string $studentTz, array $firsts, string $time, string $now): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($now, 'UTC'));
    $t = test();
    $t->academy = $t->actAsTenant($t->createAcademy(['slug' => 'zones']));
    $t->subject = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $t->admin = User::factory()->withRole(Role::Admin)->create();
    $t->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => $teacherTz]);
    $t->student = User::factory()->withRole(Role::Student)->create(['timezone' => $studentTz]);
    app(PeopleApi::class)->saveStudentProfile($t->student->id, CarbonImmutable::parse('1995-05-01'), null);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($t->teacher->id, 'female', ['ar'], [$t->subject]));

    $schedule = ShiftSchedule::query()->create(['teacher_id' => $t->teacher->id, 'version' => 1, 'timezone' => $teacherTz, 'effective' => '[2026-09-01,)']);
    $times = [];
    foreach ($firsts as $first) {
        $start = CarbonImmutable::parse("{$first} {$time}", $teacherTz);
        $schedule->rules()->create(['teacher_id' => $t->teacher->id, 'weekday' => $start->dayOfWeekIso, 'local_start' => $start->subHours(2)->format('H:i'), 'duration_min' => 300]);
        for ($i = 0; $i < 8; $i++) {
            $times[] = $start->addWeeks($i)->getTimestamp();
        }
    }

    $region = PriceRegion::query()->create(['name_ar' => 'عام', 'name_en' => 'General', 'currency' => 'USD', 'is_default' => true]);
    $plan = Plan::query()->create(['name_ar' => 'باقة', 'name_en' => 'Plan', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => count($firsts), 'validity_days' => 42]);
    $plan->prices()->create(['price_region_id' => $region->id, 'amount_minor' => 8000, 'currency' => 'USD']);

    $hold = app(PlaceBookingHold::class)->handle($t->teacher->id, $t->student->id, $times, 30, $t->admin->id);
    $t->booked = app(EnrollStudent::class)->handle($hold, $t->student->id, $t->subject, new EnrollmentTerms($plan->id, $t->student->id, $region->id), $t->admin->id);
}

/** @return list<array{string, string, string, int, string, ?string}> changes_at, the student's hour before and after, by how much, whose clock, until when */
function zoneNotices(): array
{
    return ShiftNotice::query()->orderBy('changes_at')->get()->map(fn (ShiftNotice $n) => [
        $n->changes_at->toIso8601ZuluString(), substr($n->before_time, 0, 5), substr($n->after_time, 0, 5), $n->delta_min, $n->cause_tz, $n->reverts_at?->toIso8601ZuluString(),
    ])->all();
}

function studentHourOn(string $teacherDate): ?string
{
    $t = test();

    return Session::query()->where('enrollment_id', $t->booked->enrollmentId)->get()
        ->first(fn (Session $s) => $s->starts_at->setTimezone($t->teacher->timezone)->toDateString() === $teacherDate)
        ?->starts_at->setTimezone($t->student->timezone)->format('H:i');
}

it('moves a Casablanca student\'s hour for Ramadan and back, with a Cairo teacher', function () {
    zonedCourse('Africa/Cairo', 'Africa/Casablanca', ['2027-01-18'], '19:00', '2027-01-15 08:00');

    expect(app(PlanDstChanges::class)->handle())->toBe(1)
        ->and(zoneNotices())->toBe([
            // Morocco goes back an hour: 17:00 instead of 18:00, until it goes forward on 14 March.
            ['2027-02-08T17:00:00Z', '18:00', '17:00', -60, 'Africa/Casablanca', '2027-03-14T02:00:00Z'],
        ]);

    // Fourteen days before, the family hears it.
    CarbonImmutable::setTestNow('2027-01-26 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(1)
        ->and(Notification::query()->where('user_id', $this->student->id)->where('template_key', 'scheduling.dst_family')->count())->toBe(1);

    // A month on, the weeks ahead reach Morocco's return to UTC+1, and then Cairo's summer time.
    CarbonImmutable::setTestNow('2027-02-20 08:00 UTC');
    app(MaterializeSessions::class)->handle();
    app(PlanDstChanges::class)->handle();
    expect(zoneNotices()[1])->toBe(['2027-03-15T17:00:00Z', '17:00', '18:00', 60, 'Africa/Casablanca', '2027-04-29T22:00:00Z']);
});

it('sees no change for a New York student on the day both clocks go forward, and keeps their hour for as long as it lasts', function () {
    // A Casablanca teacher at 20:00 on Wednesdays: 14:00 in New York, 15:00 once Morocco goes back.
    zonedCourse('Africa/Casablanca', 'America/New_York', ['2027-01-20'], '20:00', '2027-01-15 08:00');

    app(PlanDstChanges::class)->handle();
    // On 14 March Morocco and New York both go forward (five hours apart): 15:00 stays 15:00, until November.
    expect(zoneNotices())->toBe([
        ['2027-02-10T20:00:00Z', '14:00', '15:00', 60, 'Africa/Casablanca', '2027-11-07T06:00:00Z'],
    ]);

    // The academy keeps the student's 14:00: the teacher's time moves to 19:00 through March and beyond.
    app(DecideDstChange::class)->handle(ShiftNotice::query()->sole(), 'keep_student', $this->admin->id);
    expect(studentHourOn('2027-02-03'))->toBe('14:00')
        ->and(studentHourOn('2027-02-10'))->toBe('14:00')
        ->and(studentHourOn('2027-03-10'))->toBe('14:00');

    // The weeks after both clocks went forward are made from the moved weekly time: still 14:00, nothing to tell.
    CarbonImmutable::setTestNow('2027-03-01 08:00 UTC');
    app(MaterializeSessions::class)->handle();
    expect(app(PlanDstChanges::class)->handle())->toBe(0)
        ->and(studentHourOn('2027-03-17'))->toBe('14:00')
        ->and(studentHourOn('2027-04-21'))->toBe('14:00');
});

it('follows a New York student through Cairo going back and New York two days later', function () {
    zonedCourse('Africa/Cairo', 'America/New_York', ['2026-10-06', '2026-10-10'], '18:00', '2026-10-05 07:00');

    app(PlanDstChanges::class)->handle();

    // Tuesdays fall either side of both changes: 11:00 throughout. Saturday 31 October sits between them.
    expect(zoneNotices())->toBe([
        ['2026-10-31T16:00:00Z', '11:00', '12:00', 60, 'Africa/Cairo', '2026-11-01T06:00:00Z'],
        ['2026-11-07T16:00:00Z', '12:00', '11:00', -60, 'America/New_York', '2027-03-14T07:00:00Z'],
    ]);
});

it('moves a Dubai student, who has no summer time, when London goes back', function () {
    zonedCourse('Europe/London', 'Asia/Dubai', ['2026-10-05'], '16:00', '2026-10-01 07:00');

    app(PlanDstChanges::class)->handle();

    expect(zoneNotices())->toBe([
        ['2026-10-26T16:00:00Z', '19:00', '20:00', 60, 'Europe/London', '2027-03-28T01:00:00Z'],
    ]);
});
