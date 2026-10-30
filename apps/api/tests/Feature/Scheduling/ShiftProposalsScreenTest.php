<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Modules\Scheduling\Filament\Pages\ShiftProposals;

/*
 * The shifts screen and the teacher's view of an approved schedule that
 * starts later (docs/architecture/02, section 2): it shows as `upcoming`,
 * the admin's editor starts from it, refusals land on the blocks, and the
 * list puts what waits on the admins first.
 */

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Cairo.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['name' => 'سارة', 'email' => 'sara@example.test', 'timezone' => 'Africa/Cairo']);
    $this->admin = User::factory()->withRole(Role::Admin)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function shiftsWeek(array $blocks): WeeklyShifts
{
    return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), 'Africa/Cairo');
}

function proposeShifts(User $teacher, array $blocks, string $from): ShiftProposal
{
    return app(SaveShiftProposal::class)->handle($teacher->id, shiftsWeek($blocks), CarbonImmutable::parse($from));
}

/** Saturday 17:00–21:00 and Tuesday 16:00–21:00, approved to start on Saturday the 10th. */
function approvedLater(): ShiftProposal
{
    $proposal = proposeShifts(test()->teacher, [[6, '17:00', '21:00'], [2, '16:00', '21:00']], '2026-10-10');

    return app(ApproveShiftProposal::class)->handle($proposal->refresh(), test()->admin->id, 'admin');
}

function shiftsScreen(): Testable
{
    test()->actingAs(test()->admin);
    Filament::setCurrentPanel('academy');

    return Livewire::test(ShiftProposals::class);
}

it('shows the teacher an approved schedule that starts later as upcoming, with the last decisions', function () {
    approvedLater();
    $this->clearTenant();
    $token = $this->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => 'sara@example.test', 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    $this->withToken($token)->getJson('/api/v1/teacher/shifts')
        ->assertOk()
        ->assertJsonPath('data.current.blocks', [])
        ->assertJsonPath('data.proposal', null)
        ->assertJsonPath('data.upcoming.version', 1)
        ->assertJsonPath('data.upcoming.timezone', 'Africa/Cairo')
        ->assertJsonPath('data.upcoming.weekly_minutes', 540)
        ->assertJsonPath('data.upcoming.blocks.0', ['weekday' => 2, 'start' => '16:00', 'end' => '21:00', 'duration_min' => 300])
        ->assertJsonCount(2, 'data.upcoming.blocks')
        ->assertJsonPath('data.upcoming.starts_on.local', fn (string $local) => str_starts_with($local, '2026-10-10'))
        ->assertJsonPath('data.history.0.status', 'approved')
        ->assertJsonPath('data.history.0.effective_from', '2026-10-10')
        ->assertJsonPath('data.history.0.reason', null)
        ->assertJsonCount(1, 'data.history');

    // On its day it is the schedule in force, and nothing is upcoming.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 07:00', 'UTC'));
    $this->withToken($token)->getJson('/api/v1/teacher/shifts')
        ->assertJsonPath('data.current.weekly_minutes', 540)
        ->assertJsonPath('data.upcoming', null);
});

it('starts the admin\'s editor from the approved schedule that starts later, and on its day', function () {
    approvedLater();

    $screen = shiftsScreen()
        ->mountAction('editTeacher')
        ->set('mountedActions.0.data.teacher_id', $this->teacher->id);
    expect(substr($screen->get('mountedActions.0.data.effective_from'), 0, 10))->toBe('2026-10-10')
        ->and(array_values($screen->get('mountedActions.0.data.blocks')))->toBe([
            ['weekday' => 2, 'start' => '16:00', 'end' => '21:00'],
            ['weekday' => 6, 'start' => '17:00', 'end' => '21:00'],
        ]);

    // The teachers' tab says what is approved to come.
    app()->setLocale('ar');
    shiftsScreen()->set('tab', 'teachers')->assertSee(['سارة', 'لا يوجد جدول معتمد', 'جدول جديد من السبت 10 أكتوبر (9 ساعة)']);

    // The same from the teacher's schedule: "Edit" opens on it.
    shiftsScreen()
        ->mountAction(TestAction::make('teacher')->arguments(['teacher' => $this->teacher->id]))
        ->assertMountedActionModalSee(['سارة', '10']);
    $fromTeacher = shiftsScreen()->mountAction([TestAction::make('teacher')->arguments(['teacher' => $this->teacher->id]), TestAction::make('edit')]);
    expect(substr($fromTeacher->get('mountedActions.1.data.effective_from'), 0, 10))->toBe('2026-10-10')
        ->and(count($fromTeacher->get('mountedActions.1.data.blocks')))->toBe(2);

    // With nothing approved, it starts empty from today (the academy may start a schedule today).
    $other = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $empty = shiftsScreen()->mountAction('editTeacher')->set('mountedActions.0.data.teacher_id', $other->id);
    expect(substr($empty->get('mountedActions.0.data.effective_from'), 0, 10))->toBe('2026-10-05')
        ->and($empty->get('mountedActions.0.data.blocks'))->toBe([]);
});

it('sends the edited schedule as a counter-offer and opens it in place', function () {
    approvedLater();

    shiftsScreen()
        ->callAction('editTeacher', data: [
            'teacher_id' => $this->teacher->id,
            'blocks' => [['weekday' => 6, 'start' => '17:00', 'end' => '22:00'], ['weekday' => 2, 'start' => '16:00', 'end' => '21:00']],
            'effective_from' => '2026-10-10',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirectContains('proposal=');

    $proposal = ShiftProposal::query()->where('status', ShiftProposalStatus::Countered->value)->sole();
    expect($proposal->shifts()->weeklyMinutes())->toBe(600);
});

it('shows a refused schedule on the blocks field, not nowhere', function () {
    shiftsScreen()
        ->callAction('editTeacher', data: [
            'teacher_id' => $this->teacher->id,
            'blocks' => [['weekday' => 6, 'start' => '17:00', 'end' => '17:30']],
            'effective_from' => '2026-10-06',
        ])
        ->assertHasActionErrors(['blocks']);

    // The counter-offer from a proposal's slide-over too.
    $proposal = proposeShifts($this->teacher, [[6, '17:00', '21:00']], '2026-10-06');
    shiftsScreen()
        ->mountAction([TestAction::make('open')->arguments(['proposal' => $proposal->id]), TestAction::make('counterOffer')])
        ->set('mountedActions.1.data.blocks', [['weekday' => 6, 'start' => '17:00', 'end' => '17:30']])
        ->callMountedAction()
        ->assertHasErrors(['mountedActions.1.data.blocks']);

    expect(ShiftProposal::query()->count())->toBe(1)
        ->and($proposal->refresh()->status)->toBe(ShiftProposalStatus::Submitted);
});

it('groups proposals by status, what waits for review first and the oldest on top', function () {
    $teachers = collect(['أحمد', 'ليلى', 'منى', 'خالد'])->map(fn (string $name) => User::factory()->withRole(Role::Teacher)->create(['name' => $name, 'timezone' => 'Africa/Cairo']));

    proposeShifts($teachers[0], [[6, '17:00', '21:00']], '2026-10-06');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 09:00', 'UTC'));
    proposeShifts($teachers[1], [[1, '17:00', '21:00']], '2026-10-06');
    app(CounterShiftProposal::class)->handle($teachers[2]->id, shiftsWeek([[3, '16:00', '20:00']]), CarbonImmutable::parse('2026-10-07'), null, $this->admin->id);
    app(ApproveShiftProposal::class)->handle(proposeShifts($teachers[3], [[4, '16:00', '20:00']], '2026-10-08')->refresh(), $this->admin->id, 'admin');

    $groups = shiftsScreen()->instance()->groups;

    expect(array_keys($groups))->toBe(['review', 'teacher', 'approved', 'closed'])
        ->and(array_column($groups['review'], 'teacher'))->toBe(['أحمد', 'ليلى'])
        ->and(array_column($groups['teacher'], 'teacher'))->toBe(['منى'])
        ->and(array_column($groups['approved'], 'teacher'))->toBe(['خالد'])
        ->and($groups['closed'])->toBe([])
        ->and($groups['review'][0]['summary']['days'][0]['kind'])->toBe('added')
        ->and($groups['review'][0]['conflicts'])->toBe(0);

    // A reply overdue goes on top.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 09:00', 'UTC'));
    proposeShifts($this->teacher, [[5, '10:00', '14:00']], '2026-10-10');
    expect(array_column(shiftsScreen()->instance()->groups['review'], 'overdue'))->toBe([true, true, false]);
});

it('opens a proposal in a slide-over with the two weeks, the conversation and one step to take', function () {
    $proposal = app(SaveShiftProposal::class)->handle($this->teacher->id, shiftsWeek([[6, '17:00', '21:00']]), CarbonImmutable::parse('2026-10-06'), 'متاحة السبت');
    app()->setLocale('ar');

    shiftsScreen()
        ->mountAction('open', ['proposal' => $proposal->id])
        ->assertMountedActionModalSee(['سارة', 'أرسل المعلّم المقترح', 'متاحة السبت', 'السبت', '5:00 م – 9:00 م'])
        ->assertActionVisible('approve')
        ->assertActionHidden('counter')
        ->set('reply', 'سأراجعه اليوم')
        ->call('sendReply', $proposal->id)
        ->assertHasNoErrors()
        ->assertMountedActionModalSee('سأراجعه اليوم');

    shiftsScreen()->callAction([TestAction::make('open')->arguments(['proposal' => $proposal->id]), TestAction::make('approve')]);

    expect($proposal->refresh()->status)->toBe(ShiftProposalStatus::Approved);
});

it('leads the old review address and links to the slide-over', function () {
    $proposal = proposeShifts($this->teacher, [[6, '17:00', '21:00']], '2026-10-06');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(ReviewShiftProposal::class, ['proposal' => $proposal->id])
        ->assertRedirect(ShiftProposals::getUrl(['proposal' => $proposal->id]));

    // The page opens them as it loads (Filament's default action).
    Livewire::withQueryParams(['proposal' => $proposal->id])->test(ShiftProposals::class)
        ->assertSet('defaultAction', 'open')
        ->assertSet('defaultActionArguments', ['proposal' => $proposal->id]);
    Livewire::withQueryParams(['teacher' => $this->teacher->id])->test(ShiftProposals::class)
        ->assertSet('tab', 'teachers')
        ->assertSet('defaultAction', 'teacher')
        ->assertSet('defaultActionArguments', ['teacher' => $this->teacher->id]);
});
