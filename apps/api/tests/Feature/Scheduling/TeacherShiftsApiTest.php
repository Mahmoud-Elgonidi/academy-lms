<?php

use Carbon\CarbonImmutable;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['email' => 'sara@example.test', 'timezone' => 'Africa/Cairo']);
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    User::factory()->withRole(Role::Student)->create(['email' => 'omar@example.test']);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function tokenFor(string $email): string
{
    return test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');
}

it('shows a teacher their schedule and when a change may start', function () {
    $this->withToken(tokenFor('sara@example.test'))->getJson('/api/v1/teacher/shifts')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Africa/Cairo')
        ->assertJsonPath('data.current.blocks', [])
        ->assertJsonPath('data.proposal', null)
        ->assertJsonPath('data.earliest_start.adding_time', '2026-10-06')
        ->assertJsonPath('data.earliest_start.removing_time', '2026-10-19');
});

it('lets the teacher send a weekly schedule and a message', function () {
    $token = tokenFor('sara@example.test');

    $this->withToken($token)->putJson('/api/v1/teacher/shifts/proposal', [
        'blocks' => [['weekday' => 6, 'start' => '17:00', 'end' => '21:00'], ['weekday' => 2, 'start' => '16:00', 'end' => '21:00']],
        'effective_from' => '2026-10-06',
        'note' => 'متاحة السبت والتلات',
        'max_weekly_hours' => 12,
    ])->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.waiting_on', 'admin')
        ->assertJsonPath('data.week.weekly_minutes', 540)
        ->assertJsonPath('data.comments.0.body', 'متاحة السبت والتلات')
        ->assertJsonPath('data.comments.0.mine', true);

    $this->withToken($token)->postJson('/api/v1/teacher/shifts/proposal/comments', ['body' => 'ممكن نبدأ من الأسبوع الجاي'])
        ->assertCreated()
        ->assertJsonCount(2, 'data.comments');
});

it('explains in Arabic what is wrong with a schedule', function () {
    $this->withToken(tokenFor('sara@example.test'))->withHeader('Accept-Language', 'ar')
        ->putJson('/api/v1/teacher/shifts/proposal', [
            'blocks' => [['weekday' => 6, 'start' => '17:00', 'end' => '17:30']],
            'effective_from' => '2026-10-06',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'validation')
        ->assertJsonPath('errors.shifts.0', 'الوقت اللي بيبدأ 17:00 يوم السبت أقل من 60 دقيقة.');
});

it('lets the teacher accept a counter-offer with one call', function () {
    $this->actAsTenant($this->academy);
    app(CounterShiftProposal::class)->handle(
        $this->teacher->id,
        WeeklyShifts::fromArray([['weekday' => 6, 'start' => '18:00', 'end' => '22:00']], 'Africa/Cairo'),
        CarbonImmutable::parse('2026-10-10'),
        'السبت من 6',
        $this->admin->id,
    );
    $this->clearTenant();
    $token = tokenFor('sara@example.test');

    $this->withToken($token)->getJson('/api/v1/teacher/shifts')->assertJsonPath('data.proposal.waiting_on', 'teacher');
    $this->withToken($token)->postJson('/api/v1/teacher/shifts/proposal/accept')->assertOk()->assertJsonPath('data.status', 'approved');
});

it('keeps students out of the teacher endpoints', function () {
    $this->withToken(tokenFor('omar@example.test'))->getJson('/api/v1/teacher/shifts')->assertForbidden();
});
