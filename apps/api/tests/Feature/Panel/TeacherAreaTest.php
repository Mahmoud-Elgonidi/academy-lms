<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Filament\Teacher\MyStudents;
use Modules\Learning\Filament\Teacher\StudentProgress;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ReviewShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\Teacher\MyAvailability;
use Modules\Scheduling\Filament\Teacher\MyWeek;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The teacher area (/teach): the week, their students, and proposing their
 * hours, with the same Actions as the teacher API.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();          // Mon 5 Oct 2026, 07:00 UTC; Cairo teacher on Tue and Sat 17:00–21:00
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows the teacher\'s own week on their clock, and moves from week to week', function () {
    Livewire::test(MyWeek::class)
        ->assertSee('6:00 PM – 6:30 PM')->assertSee($this->student->name)
        ->assertSee(trans_choice('scheduling::teacher.week.count', 2, ['n' => 2]))   // Tuesday and Saturday
        ->call('move', -1)->assertSee(__('scheduling::teacher.week.none'));

    $this->actingAs($this->other);
    Livewire::test(MyWeek::class)->assertDontSee($this->student->name);
});

it('lists the teacher\'s students, and opens only their own', function () {
    Livewire::test(MyStudents::class)->assertSee($this->student->name)->assertSee('Quran');
    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])->assertOk()->assertSee($this->student->name);

    $this->actingAs($this->other);
    Livewire::test(MyStudents::class)->assertDontSee($this->student->name);
    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])->assertNotFound();
});

it('lets the teacher propose new hours to the academy, save a draft, write to them and withdraw', function () {
    $page = Livewire::test(MyAvailability::class)
        ->assertSet('data.effective_from', '2026-10-06')
        ->assertSee(__('scheduling::shifts.days.2'));                 // the week in force: Tuesday 17:00–21:00

    $blocks = array_values($page->get('data.blocks'));
    expect($blocks)->toHaveCount(2);

    $page->assertDontSeeHtml('wire:submit="send"')                     // nothing open: "propose a change" under the week
        ->assertSee(__('scheduling::teacher.availability.propose_change'))
        ->call('startEditing')->assertSeeHtml('wire:submit="send"')
        ->set('data.blocks', [...$blocks, ['weekday' => 1, 'start' => '10:00', 'end' => '13:00']])
        ->call('send')
        ->assertNotified(__('scheduling::teacher.availability.sent'));

    $proposal = ShiftProposal::query()->where('teacher_id', $this->teacher->id)->sole();
    expect($proposal->status)->toBe(ShiftProposalStatus::Submitted)
        ->and($proposal->shifts()->weeklyMinutes())->toBe(240 * 2 + 180);

    Livewire::test(MyAvailability::class)->set('reply', 'Mondays are free from next week.')->call('comment')
        ->assertSee('Mondays are free from next week.')
        ->callAction('withdraw');
    expect($proposal->refresh()->status)->toBe(ShiftProposalStatus::Withdrawn);
});

it('says why hours were not sent, and keeps nothing', function () {
    Livewire::test(MyAvailability::class)
        ->set('data.blocks', [['weekday' => 1, 'start' => '10:00', 'end' => '10:30']])   // shorter than an hour, and Tuesday/Saturday removed at a day's notice
        ->call('send')
        ->assertNotified(__('scheduling::teacher.availability.not_sent'));

    expect(ShiftProposal::query()->where('teacher_id', $this->teacher->id)->where('status', '<>', 'draft')->exists())->toBeFalse();
});

it('shows the teacher a schedule approved to start later on top, and one week card that switches between now and then', function () {
    // Monday added from Monday the 12th, approved by the office: today's week stays until then.
    $proposal = app(SaveShiftProposal::class)->handle($this->teacher->id, WeeklyShifts::fromArray([
        ['weekday' => 2, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 6, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 1, 'start' => '10:00', 'end' => '13:00'],
    ], 'Africa/Cairo'), CarbonImmutable::parse('2026-10-12'));
    app(ApproveShiftProposal::class)->handle($proposal->refresh(), User::factory()->withRole(Role::Admin)->create()->id, 'admin');
    $l = 'scheduling::teacher.availability.';

    Livewire::test(MyAvailability::class)
        ->assertSee(__($l.'hero.upcoming', ['date' => 'Monday 12 October']))
        ->assertSee(trans_choice($l.'hero.in_days', 7, ['count' => 7]))
        ->assertSeeHtml('data-count="8"')->assertSeeHtml('data-count="11"')     // the hours a week count up, before and after
        ->assertSee(__($l.'now'))->assertSee(__($l.'from_day', ['date' => '12 October']))
        ->assertSeeHtml('data-mark="added"')                                // Monday's new hours, marked in the later week
        ->assertSeeHtml('lms-bar-fill')
        ->assertDontSeeHtml('aria-current="step"')                          // approved: no steps on top
        ->assertDontSee(__($l.'no_current'))
        ->assertSee(__($l.'propose_change'))->assertSee(__($l.'built_on', ['date' => '12 October']))
        ->assertSee(__($l.'history', ['n' => 1]))
        ->assertSet('data.effective_from', '2026-10-12')
        ->assertCount('data.blocks', 3);
});

it('shows only the later week, with no hours a week of nothing, when none are approved before it', function () {
    $new = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $proposal = app(SaveShiftProposal::class)->handle($new->id, WeeklyShifts::fromArray([
        ['weekday' => 1, 'start' => '10:00', 'end' => '13:00'],
    ], 'Africa/Cairo'), CarbonImmutable::parse('2026-10-12'));
    app(ApproveShiftProposal::class)->handle($proposal->refresh(), $this->admin->id, 'admin');
    $l = 'scheduling::teacher.availability.';

    $this->actingAs($new);
    Livewire::test(MyAvailability::class)
        ->assertSee(__($l.'hero.upcoming', ['date' => 'Monday 12 October']))
        ->assertSee(__($l.'no_hours_before', ['date' => '12 October']))
        ->assertDontSee(__($l.'now'))
        ->assertDontSee(__($l.'hours', ['h' => 0]))
        ->assertDontSee(__($l.'no_current'))
        ->assertDontSeeHtml('data-count="0"')
        ->assertDontSeeHtml('aria-current="step"');
});

it('shows an open proposal in steps, with the conversation as bubbles and its steps between them', function () {
    Livewire::test(MyAvailability::class)->call('send');
    $l = 'scheduling::teacher.availability.';
    Livewire::test(MyAvailability::class)
        ->set('reply', 'Saturday mornings work too.')->call('comment')
        ->assertSeeHtml('aria-current="step"')
        ->assertSee(__($l.'steps.review'))
        ->assertSee(__($l.'hero.submitted.title'))->assertSee(__($l.'hero.submitted.text'))
        ->assertSeeHtml('ta-pulse')
        ->assertSee(__($l.'conversation_count', ['n' => 1]))
        ->assertSee(__($l.'sent_event'))
        ->assertSeeHtml('data-mine="true"')
        ->assertSee('Saturday mornings work too.')
        ->assertDontSee(__($l.'propose_change'));                          // one proposal at a time
});

it('opens a draft with one thing to do: finish and send', function () {
    Livewire::test(MyAvailability::class)->call('saveDraft')->assertNotified(__('scheduling::teacher.availability.saved'));
    $l = 'scheduling::teacher.availability.';

    Livewire::test(MyAvailability::class)
        ->assertSee(__($l.'hero.draft.title'))
        ->assertSee(__($l.'continue'))
        ->assertDontSee(__($l.'withdraw'))
        ->assertDontSee(__($l.'conversation_count', ['n' => 0]))
        ->assertDontSeeHtml('wire:submit="send"')
        ->call('startEditing')->assertSeeHtml('wire:submit="send"')
        ->call('send')->assertNotified(__($l.'sent'));

    expect(ShiftProposal::query()->where('teacher_id', $this->teacher->id)->sole()->status)->toBe(ShiftProposalStatus::Submitted);
});

it('shows the academy\'s counter-offer with what changed, to accept as it is', function () {
    Livewire::test(MyAvailability::class)->call('send');
    app(CounterShiftProposal::class)->handle($this->teacher->id, WeeklyShifts::fromArray([
        ['weekday' => 2, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 6, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 4, 'start' => '09:00', 'end' => '11:00'],
    ], 'Africa/Cairo'), CarbonImmutable::parse('2026-10-12'), null, $this->admin->id);
    $l = 'scheduling::teacher.availability.';

    Livewire::test(MyAvailability::class)
        ->assertSee(__($l.'hero.countered.title'))
        ->assertSee(__($l.'counter_week'))
        ->assertSeeHtml('data-mark="added"')                                // Thursday, offered by the academy
        ->assertActionVisible('accept')
        ->assertSee(__($l.'edit'))
        ->callAction('accept')
        ->assertNotified(__($l.'accepted'))
        ->assertSee(__($l.'hero.upcoming', ['date' => 'Monday 12 October']));
});

it('says on top why the last proposal was not approved, and keeps replaced ones in the history alone', function () {
    Livewire::test(MyAvailability::class)->call('send');
    $proposal = ShiftProposal::query()->where('teacher_id', $this->teacher->id)->sole();
    app(ReviewShiftProposal::class)->reject($proposal, 'Saturdays are full.', $this->admin->id);
    $l = 'scheduling::teacher.availability.';

    Livewire::test(MyAvailability::class)
        ->assertSee(__($l.'hero.rejected.title'))
        ->assertSee(__($l.'hero.reason', ['reason' => 'Saturdays are full.']))
        ->assertSee(__($l.'propose_change'))
        ->assertSee(__($l.'history', ['n' => 1]))
        ->assertDontSee(__($l.'superseded'));

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(15));
    Livewire::test(MyAvailability::class)->assertDontSee(__($l.'hero.rejected.title'));
});
