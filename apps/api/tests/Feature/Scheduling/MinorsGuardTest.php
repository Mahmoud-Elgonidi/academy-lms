<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\BookExtraLesson;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\ReassignTeacher;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\ExistingSchedule;
use Modules\Scheduling\Contracts\ScheduleImport;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Every door that books a lesson asks MinorsGuard (docs/architecture/02,
 * section 15): a teacher paused while a concern is looked into gets no new
 * lesson with a child, whichever way it comes, even from a time held or a
 * course begun before the pause. The fixture's adult student and a
 * ten-year-old with a guardian, and the Cairo teacher who teaches them.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->child = User::factory()->withRole(Role::Student)->create(['timezone' => 'Africa/Cairo']);
    $this->parent = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::parse('2016-03-01'), null);
    app(PeopleApi::class)->linkGuardian($this->parent->id, $this->child->id);
    $this->paused = __('scheduling::policy.errors.not_cleared', ['state' => __('people::checks.clearance.paused')]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The child's weekly Tuesday and Saturday 19:00 (Cairo) held with a teacher, eight weeks. */
function holdChildWeeks(string $teacherId, string $coordinatorId): string
{
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse("{$first} 19:00", 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }

    return app(PlaceBookingHold::class)->handle($teacherId, test()->child->id, $times, 30, $coordinatorId);
}

function minorsEnrolChild(string $hold): EnrollmentBooked
{
    return app(EnrollStudent::class)->handle($hold, test()->child->id, test()->quran, new EnrollmentTerms(Plan::query()->value('id'), test()->parent->id, PriceRegion::query()->value('id')), test()->admin->id);
}

function pauseForConcern(User $teacher): void
{
    app(PeopleApi::class)->pauseForMinors($teacher->id, 'Concern #7 is being looked into', test()->admin->id);
}

it('books nothing from a time held before the teacher was paused: no course, no trial', function () {
    $weeks = holdChildWeeks($this->teacher->id, $this->admin->id);
    $coordinator = User::factory()->withRole(Role::Admin)->create();
    $trial = app(PlaceBookingHold::class)->handle($this->teacher->id, $this->child->id, [CarbonImmutable::parse('2026-10-10 20:00', 'Africa/Cairo')->getTimestamp()], 30, $coordinator->id);

    pauseForConcern($this->teacher);

    expect(fn () => minorsEnrolChild($weeks))->toThrow(ValidationException::class, $this->paused)
        ->and(fn () => app(ConfirmBooking::class)->trial($trial, $this->child->id, $this->quran, $coordinator->id))->toThrow(ValidationException::class, $this->paused)
        ->and(Session::query()->whereHas('participants', fn ($q) => $q->where('user_id', $this->child->id))->count())->toBe(0);
});

it('gives a paused teacher no new lesson in a child\'s course: no makeup, no extra lesson, and the course goes to no paused teacher', function () {
    $course = minorsEnrolChild(holdChildWeeks($this->teacher->id, $this->admin->id));
    app(RecordPayment::class)->handle($course->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $lessons = Session::query()->count();

    pauseForConcern($this->teacher);
    $at = CarbonImmutable::parse('2026-10-06 20:00', 'Africa/Cairo');

    expect(fn () => app(BookMakeup::class)->handle($course->enrollmentId, $at, $this->admin->id))->toThrow(ValidationException::class, $this->paused)
        ->and(fn () => app(BookExtraLesson::class)->handle($course->enrollmentId, $at, $this->admin->id))->toThrow(ValidationException::class, $this->paused);

    app(PeopleApi::class)->resumeForMinors($this->teacher->id, 'Looked into: nothing found', $this->admin->id);
    pauseForConcern($this->other);
    expect(fn () => app(ReassignTeacher::class)->handle($course->enrollmentId, $this->other->id, CarbonImmutable::parse('2026-10-12'), $this->admin->id))
        ->toThrow(ValidationException::class, $this->paused)
        ->and(Session::query()->count())->toBe($lessons);
});

it('brings a child\'s existing lessons over only to a teacher cleared for children, and an adult\'s as before (import)', function () {
    pauseForConcern($this->teacher);
    $adult = User::factory()->withRole(Role::Student)->create(['timezone' => 'Africa/Cairo']);
    app(PeopleApi::class)->saveStudentProfile($adult->id, CarbonImmutable::parse('1990-01-01'), null);
    $schedule = fn (User $student) => new ExistingSchedule($student->id, $this->teacher->id, $this->quran, 30, [['weekday' => 2, 'time' => '19:00'], ['weekday' => 6, 'time' => '19:00']], null, CarbonImmutable::parse('2026-10-06'), remainingLessons: 4, actorId: $this->admin->id);

    expect(fn () => app(ScheduleImport::class)->bring($schedule($this->child)))->toThrow(ValidationException::class, $this->paused)
        ->and(app(ScheduleImport::class)->exists($this->child->id, $this->teacher->id, $this->quran))->toBeFalse()
        ->and(app(ScheduleImport::class)->bring($schedule($adult))->sessions)->toBeGreaterThan(0);
});
