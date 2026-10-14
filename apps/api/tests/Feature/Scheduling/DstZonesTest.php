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
 * good (Sunday 20 September 2026, 02:00 → 01:00; tzdata 2026c, no more
 * Ramadan hour); London and Beirut go forward the same night, four hours
 * apart (27–28 March 2027); New York goes back on 1 November 2026, two days
 * after Cairo, and forward on 14 March 2027; Dubai and Riyadh never change.
 * Weekly times stay on the teacher's clock.
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

it('moves a Casablanca student\'s hour when Morocco stays on UTC+0 for good, and again when Cairo goes back', function () {
    zonedCourse('Africa/Cairo', 'Africa/Casablanca', ['2026-09-07'], '19:00', '2026-09-04 08:00');

    expect(app(PlanDstChanges::class)->handle())->toBe(1)
        ->and(zoneNotices())->toBe([
            // Morocco goes back an hour and stays there: 16:00 instead of 17:00, until Cairo goes back too.
            ['2026-09-21T16:00:00Z', '17:00', '16:00', -60, 'Africa/Casablanca', '2026-10-29T21:00:00Z'],
        ]);

    // Fourteen days before, the family hears it.
    CarbonImmutable::setTestNow('2026-09-08 09:00 UTC');
    expect(app(SendDstNotices::class)->handle())->toBe(1)
        ->and(Notification::query()->where('user_id', $this->student->id)->where('template_key', 'scheduling.dst_family')->count())->toBe(1);

    // A month on, the weeks ahead reach Cairo's winter time: 17:00 again, until Cairo goes forward in April.
    CarbonImmutable::setTestNow('2026-10-10 08:00 UTC');
    app(MaterializeSessions::class)->handle();
    app(PlanDstChanges::class)->handle();
    expect(zoneNotices()[1])->toBe(['2026-11-02T17:00:00Z', '16:00', '17:00', 60, 'Africa/Cairo', '2027-04-29T22:00:00Z']);
});

it('sees no change for a Beirut student when both clocks go forward the same night, hours apart', function () {
    // A London teacher at 16:00 on Wednesdays: 18:00 in Beirut before and after the last weekend of March.
    zonedCourse('Europe/London', 'Asia/Beirut', ['2027-03-03'], '16:00', '2027-02-26 08:00');

    expect(app(PlanDstChanges::class)->handle())->toBe(0)
        ->and(studentHourOn('2027-03-24'))->toBe('18:00')
        ->and(studentHourOn('2027-03-31'))->toBe('18:00')
        // Nor do the hours between the two changes count as a change, in October or in March.
        ->and(PlanDstChanges::revertsAt('Europe/London', 'Asia/Beirut', CarbonImmutable::parse('2026-10-01 00:00', 'UTC')))->toBeNull();
});

it('keeps a New York student\'s hour for as long as it lasts, with a Casablanca teacher on UTC+0 all year', function () {
    // A Casablanca teacher at 20:00 on Wednesdays: 15:00 in New York, 16:00 once New York goes forward on 14 March.
    zonedCourse('Africa/Casablanca', 'America/New_York', ['2027-02-10'], '20:00', '2027-02-05 08:00');

    app(PlanDstChanges::class)->handle();
    expect(zoneNotices())->toBe([
        ['2027-03-17T20:00:00Z', '15:00', '16:00', 60, 'America/New_York', '2027-11-07T06:00:00Z'],
    ]);

    // The academy keeps the student's 15:00: the teacher's time moves to 19:00 until November.
    app(DecideDstChange::class)->handle(ShiftNotice::query()->sole(), 'keep_student', $this->admin->id);
    expect(studentHourOn('2027-03-10'))->toBe('15:00')
        ->and(studentHourOn('2027-03-17'))->toBe('15:00')
        ->and(studentHourOn('2027-03-31'))->toBe('15:00');

    // The weeks after are made from the moved weekly time: still 15:00, nothing to tell.
    CarbonImmutable::setTestNow('2027-03-20 08:00 UTC');
    app(MaterializeSessions::class)->handle();
    expect(app(PlanDstChanges::class)->handle())->toBe(0)
        ->and(studentHourOn('2027-04-14'))->toBe('15:00')
        ->and(studentHourOn('2027-04-21'))->toBe('15:00');
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
