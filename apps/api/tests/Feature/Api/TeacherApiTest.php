<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

/*
 * The teacher app's API: my lessons day by day, my students, my time off
 * (ask, withdraw), my pay for a month, my own numbers, my trial lessons.
 * The fixture: Monday 5 October 2026, a Cairo teacher with a London student,
 * Tuesdays and Saturdays 18:00 Cairo, paid.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function teacherApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

/** The lesson on that Cairo day taught in full, then closed by the deadlines. */
function taughtOn(string $day): Session
{
    test()->actAsTenant(test()->academy);
    $lesson = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse($day.' 18:00', 'Africa/Cairo')->utc())->firstOrFail();
    CarbonImmutable::setTestNow($lesson->starts_at);
    foreach ([test()->student, test()->teacher] as $person) {
        app(RecordParticipantJoin::class)->handle($lesson->refresh(), $person->id, $lesson->starts_at);
    }
    foreach ([test()->student, test()->teacher] as $person) {
        app(RecordParticipantLeave::class)->handle($lesson->refresh(), $person->id, $lesson->ends_at);
    }
    CarbonImmutable::setTestNow($lesson->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();
    $lesson->refresh();
    test()->clearTenant();

    return $lesson;
}

it('lists my lessons day by day on my clock, with the student', function () {
    $token = teacherApp($this->teacher);

    $week = $this->withToken($token)->getJson('/api/v1/teacher/lessons')
        ->assertOk()
        ->assertJsonPath('from', '2026-10-05')
        ->assertJsonPath('to', '2026-10-11')
        ->assertJsonPath('count', 2)
        ->assertJsonPath('minutes', 60)
        ->json();
    expect($week['data'][0]['with'])->toBe($this->student->name)
        ->and($week['data'][0]['starts_at']['local'])->toBe('2026-10-06T18:00:00+03:00')
        ->and($week['data'][0]['can_cancel'])->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/teacher/lessons?from=2026-10-12&days=14')->assertOk()->assertJsonPath('count', 4);
    $this->withToken($token)->getJson('/api/v1/teacher/lessons?days=90')->assertUnprocessable();
});

it('opens one of my lessons by its id, on whatever day it is, and nobody else\'s', function () {
    $token = teacherApp($this->teacher);
    $later = $this->withToken($token)->getJson('/api/v1/teacher/lessons?from=2026-10-12&days=14')->json('data.0');

    // A lesson of another week opens as the list has it.
    $this->withToken($token)->getJson("/api/v1/teacher/lessons/{$later['id']}")->assertOk()
        ->assertJsonPath('data.id', $later['id'])->assertJsonPath('data.with', $this->student->name)
        ->assertJsonPath('data.starts_at', $later['starts_at']);

    $this->withToken($token)->getJson('/api/v1/teacher/lessons/not-a-lesson')->assertNotFound();
    $this->actAsTenant($this->academy);
    $other = User::factory()->withRole(Role::Teacher)->create();
    $this->clearTenant();
    $this->withToken(teacherApp($other))->getJson("/api/v1/teacher/lessons/{$later['id']}")->assertNotFound();
});

it('lists my students with where they are and what waits for me', function () {
    $this->withToken(teacherApp($this->teacher))->getJson('/api/v1/teacher/students')
        ->assertOk()
        ->assertJsonPath('data.0.student_id', $this->student->id)
        ->assertJsonPath('data.0.enrollment_id', $this->booked->enrollmentId)
        ->assertJsonPath('data.0.to_mark', 0)
        ->assertJsonPath('data.0.last_report_at', null);

    $this->withToken(teacherApp($this->other))->getJson('/api/v1/teacher/students')->assertOk()->assertJsonCount(0, 'data');
});

it('asks for time off on my clock, shows the answer, and withdraws it while it waits', function () {
    $token = teacherApp($this->teacher);

    $asked = $this->withToken($token)->postJson('/api/v1/teacher/time-off', [
        'first_day' => '2026-10-13', 'last_day' => '2026-10-14', 'reason' => 'vacation', 'note' => 'Family wedding',
    ], ['Idempotency-Key' => 'leave-1'])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.can_withdraw', true)->json('data');
    expect($asked['starts_at']['local'])->toBe('2026-10-13T00:00:00+03:00');

    // The app retried: still one request.
    $this->withToken($token)->postJson('/api/v1/teacher/time-off', [
        'first_day' => '2026-10-13', 'last_day' => '2026-10-14', 'reason' => 'vacation', 'note' => 'Family wedding',
    ], ['Idempotency-Key' => 'leave-1'])->assertCreated()->assertJsonPath('data.id', $asked['id']);

    $this->withToken($token)->postJson('/api/v1/teacher/time-off', ['first_day' => '2026-10-15', 'from' => '17:00', 'to' => '19:00', 'reason' => 'personal'])
        ->assertCreated()->assertJsonPath('data.all_day', false);
    $this->withToken($token)->postJson('/api/v1/teacher/time-off', ['first_day' => '2026-10-01', 'reason' => 'sick'])->assertUnprocessable();

    $this->withToken($token)->getJson('/api/v1/teacher/time-off')->assertOk()->assertJsonCount(2, 'data');

    $this->withToken(teacherApp($this->other))->deleteJson("/api/v1/teacher/time-off/{$asked['id']}")->assertNotFound();
    $this->withToken(teacherApp($this->teacher))->deleteJson("/api/v1/teacher/time-off/{$asked['id']}")->assertNoContent();

    $this->actAsTenant($this->academy);
    expect(TeacherTimeOff::query()->find($asked['id'])->status->value)->toBe('withdrawn');
});

it('shows my pay for a month, lesson by lesson, and never to a parent', function () {
    $this->actAsTenant($this->academy);
    app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [['unit' => 'hour', 'amount' => '120']]]);
    $this->clearTenant();
    taughtOn('2026-10-06');

    $pay = $this->withToken(teacherApp($this->teacher))->getJson('/api/v1/teacher/earnings?month=2026-10')
        ->assertOk()
        ->assertJsonPath('month', '2026-10')
        ->assertJsonPath('closed', false)
        ->assertJsonPath('totals.0.lessons', 1)
        ->assertJsonPath('totals.0.total.minor', 6000)
        ->assertJsonPath('totals.0.total.currency', 'EGP')
        ->json();
    expect($pay['lessons'])->toHaveCount(1);

    $this->actAsTenant($this->academy);
    $parent = User::factory()->withRole(Role::Guardian)->create();
    $this->clearTenant();
    $this->withToken(teacherApp($parent))->getJson('/api/v1/teacher/earnings')->assertForbidden();
});

it('shows my own numbers and ratings without names, and my trial lessons', function () {
    taughtOn('2026-10-06');
    $token = teacherApp($this->teacher);

    $mine = $this->withToken($token)->getJson('/api/v1/teacher/performance')->assertOk()->assertJsonPath('days', 90)->json();
    expect(collect($mine['kpis'])->pluck('key'))->toContain('punctuality')
        ->and($mine['ratings'])->toHaveKeys(['avg', 'count', 'list']);

    $this->withToken($token)->getJson('/api/v1/teacher/trials')->assertOk()
        ->assertJsonStructure(['to_assess', 'upcoming', 'done', 'plans' => [['id', 'name']]]);
    $this->withToken($token)->putJson('/api/v1/teacher/trials/'.CarbonImmutable::now()->format('Ymd').'/assessment', [])->assertNotFound();

    $this->actAsTenant($this->academy);
    $parent = User::factory()->withRole(Role::Guardian)->create();
    $this->clearTenant();
    $this->withToken(teacherApp($parent))->getJson('/api/v1/teacher/performance')->assertForbidden();
});

it('lets a teacher set, read and remove their own Zoom or Meet link, and nobody else', function () {
    $token = teacherApp($this->teacher);

    $this->withToken($token)->getJson('/api/v1/teacher/meeting-link')->assertOk()->assertJsonPath('url', null)->assertJsonStructure(['url', 'used', 'label', 'hint']);
    $this->withToken($token)->putJson('/api/v1/teacher/meeting-link', ['url' => 'http://meet.google.com/abc-defg-hij'])->assertUnprocessable();
    $this->withToken($token)->putJson('/api/v1/teacher/meeting-link', ['url' => 'https://meet.google.com/abc-defg-hij'])->assertOk()
        ->assertJsonPath('url', 'https://meet.google.com/abc-defg-hij');
    $this->withToken($token)->getJson('/api/v1/teacher/meeting-link')->assertJsonPath('url', 'https://meet.google.com/abc-defg-hij');
    $this->withToken($token)->putJson('/api/v1/teacher/meeting-link', ['url' => ''])->assertOk()->assertJsonPath('url', null);
    $this->withToken($token)->putJson('/api/v1/teacher/meeting-link', [])->assertUnprocessable();

    $this->actAsTenant($this->academy);
    $parent = User::factory()->withRole(Role::Guardian)->create();
    $this->clearTenant();
    $this->withToken(teacherApp($parent))->getJson('/api/v1/teacher/meeting-link')->assertForbidden();
    $this->withToken(teacherApp($parent))->putJson('/api/v1/teacher/meeting-link', ['url' => 'https://zoom.us/j/1'])->assertForbidden();
});
