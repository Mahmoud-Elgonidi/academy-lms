<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Filament\Teacher\MyStudents;
use Modules\Learning\Filament\Teacher\StudentProgress;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
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

    $page->set('data.blocks', [...$blocks, ['weekday' => 1, 'start' => '10:00', 'end' => '13:00']])
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

it('shows the teacher a schedule approved to start later, and starts the next proposal from it', function () {
    // Monday added from Monday the 12th, approved by the office: today's week stays until then.
    $proposal = app(SaveShiftProposal::class)->handle($this->teacher->id, WeeklyShifts::fromArray([
        ['weekday' => 2, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 6, 'start' => '17:00', 'end' => '21:00'],
        ['weekday' => 1, 'start' => '10:00', 'end' => '13:00'],
    ], 'Africa/Cairo'), CarbonImmutable::parse('2026-10-12'));
    app(ApproveShiftProposal::class)->handle($proposal->refresh(), User::factory()->withRole(Role::Admin)->create()->id, 'admin');

    Livewire::test(MyAvailability::class)
        ->assertSee(__('scheduling::teacher.availability.upcoming', ['date' => 'Monday 12 October']))
        ->assertSee(__('scheduling::review.summary.hours', ['before' => 8, 'after' => 11, 'arrow' => __('scheduling::shifts.arrow')]))
        ->assertSeeHtml('data-kind="added"')                                // Monday's new hours in the grid
        ->assertSet('data.effective_from', '2026-10-12')
        ->assertCount('data.blocks', 3)
        ->assertDontSee(__('scheduling::teacher.availability.your_proposal'));
});

it('shows an open proposal in steps, with the conversation as bubbles and its steps between them', function () {
    Livewire::test(MyAvailability::class)->call('send');
    Livewire::test(MyAvailability::class)
        ->set('reply', 'Saturday mornings work too.')->call('comment')
        ->assertSeeHtml('aria-current="step"')
        ->assertSee(__('scheduling::teacher.availability.steps.review'))
        ->assertSee(__('scheduling::teacher.availability.review_hint'))
        ->assertSee(__('scheduling::teacher.availability.sent_event'))
        ->assertSeeHtml('data-mine="true"')
        ->assertSee('Saturday mornings work too.');
});
