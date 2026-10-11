<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Actions\JoinLesson;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Coming into a lesson (docs/architecture/01, section 9; 02, sections 10 and
 * 15): a child whose account waits for a guardian's consent stays out by
 * their own link (a guardian can bring them in), and a student comes in with
 * the camera the academy chose (on, off, or off for under-18s); a guardian at
 * the child's device is the child. The fixture's adult lesson is Tuesday
 * 6 October 15:00 UTC; a ten-year-old's is at 16:00 with the same teacher.
 */

beforeEach(function () {
    Mail::fake();
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    $this->enrolStudentOnPlan();
    $this->adultLesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();
    $this->child = User::factory()->withRole(Role::Student)->create(['timezone' => 'Africa/Cairo']);
    $this->parent = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::parse('2016-03-01'), null);
    app(PeopleApi::class)->linkGuardian($this->parent->id, $this->child->id);
    $this->childLesson = app(ScheduleSession::class)->handle(new NewSession($this->teacher->id, $this->child->id, $this->quran, CarbonImmutable::parse('2026-10-06 16:00 UTC'), 30, 'Africa/Cairo'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function seatAt(Session $lesson, User $user, string $time = '16:00'): array
{
    return app(JoinLesson::class)->handle($lesson->id, $user->id, CarbonImmutable::parse("2026-10-06 {$time} UTC"))->toArray();
}

it('keeps a child waiting for consent out by their own link, lets their guardian bring them, and lets them in once agreed', function () {
    expect(fn () => seatAt($this->childLesson, $this->child))->toThrow(ValidationException::class, __('classroom::classroom.refused.consent'))
        ->and(seatAt($this->childLesson, $this->parent))->toMatchArray(['role' => 'student', 'identity' => $this->child->id]);

    CarbonImmutable::setTestNow('2026-10-06 15:55 UTC');
    $link = app(NotificationLinks::class)->personal(['screen' => 'session', 'id' => $this->childLesson->id], $this->child->id);
    $this->clearTenant();
    $page = $this->get($link)->assertForbidden()->getContent();
    expect(str_contains($page, e(__('classroom::classroom.refused.consent', [], 'ar'))) || str_contains($page, e(__('classroom::classroom.refused.consent', [], 'en'))))->toBeTrue();

    $this->actAsTenant($this->academy);
    app(GrantConsent::class)->handle($this->child->id, $this->parent->id, 'family');
    expect(seatAt($this->childLesson, $this->child))->toMatchArray(['role' => 'student', 'identity' => $this->child->id]);
});

it('brings students in with the camera the academy chose: off for under-18s leaves adults and teachers on', function () {
    app(GrantConsent::class)->handle($this->child->id, $this->parent->id, 'family');
    $cameras = fn () => [
        'child' => seatAt($this->childLesson, $this->child)['camera'],
        'guardian' => seatAt($this->childLesson, $this->parent)['camera'],
        'teacher' => seatAt($this->childLesson, $this->teacher)['camera'],
        'adult' => seatAt($this->adultLesson, $this->student, '15:00')['camera'],
        'supervisor' => seatAt($this->childLesson, $this->admin)['camera'],
    ];

    expect($cameras())->toBe(['child' => true, 'guardian' => true, 'teacher' => true, 'adult' => true, 'supervisor' => false]);

    app(SaveClassroomSettings::class)->handle('livekit', 'off_for_minors');
    expect($cameras())->toBe(['child' => false, 'guardian' => false, 'teacher' => true, 'adult' => true, 'supervisor' => false]);

    app(SaveClassroomSettings::class)->handle('livekit', 'off');
    expect($cameras())->toBe(['child' => false, 'guardian' => false, 'teacher' => true, 'adult' => false, 'supervisor' => false]);
});
