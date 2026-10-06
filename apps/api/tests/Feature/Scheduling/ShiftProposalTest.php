<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ResolveShiftConflict;
use Modules\Scheduling\Actions\Shifts\ReviewShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Actions\Shifts\WithdrawShiftProposal;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\Events\ShiftRulesChanged;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Queries\ShiftImpactQuery;

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Cairo.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->admin = User::factory()->withRole(Role::Admin)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

// Saturday 17:00–21:00 and Tuesday 16:00–21:00 by default, in Cairo.
function week(array $blocks = [[6, '17:00', '21:00'], [2, '16:00', '21:00']]): WeeklyShifts
{
    return WeeklyShifts::fromArray(array_map(fn ($b) => ['weekday' => $b[0], 'start' => $b[1], 'end' => $b[2]], $blocks), 'Africa/Cairo');
}

function submit(?WeeklyShifts $shifts = null, string $from = '2026-10-06', ?string $note = null): ShiftProposal
{
    return app(SaveShiftProposal::class)->handle(test()->teacher->id, $shifts ?? week(), CarbonImmutable::parse($from), $note);
}

function approve(ShiftProposal $proposal, string $role = 'admin', ?string $override = null): ShiftProposal
{
    $actor = $role === 'teacher' ? test()->teacher->id : test()->admin->id;

    return app(ApproveShiftProposal::class)->handle($proposal->refresh(), $actor, $role, $override);
}

it('lets a teacher submit a first schedule from tomorrow', function () {
    $proposal = submit(note: 'متاحة السبت والتلات');

    expect($proposal->status)->toBe(ShiftProposalStatus::Submitted)
        ->and($proposal->revision)->toBe(1)
        ->and($proposal->shifts()->weeklyMinutes())->toBe(540)
        ->and($proposal->comments()->sole()->body)->toBe('متاحة السبت والتلات');
});

it('refuses short, overlapping or empty weeks', function (array $blocks) {
    submit(week($blocks));
})->throws(ValidationException::class)->with([
    'block under an hour' => [[[6, '17:00', '17:30']]],
    'overlap' => [[[6, '17:00', '19:00'], [6, '18:00', '20:00']]],
    'empty' => [[]],
]);

it('asks for 14 days notice before taking time away, but lets added time start tomorrow', function () {
    approve(submit());

    // Dropping Tuesday from the 6th: too soon.
    expect(fn () => submit(week([[6, '17:00', '21:00']]), '2026-10-10'))->toThrow(ValidationException::class);

    // From the 19th it is fine; adding Wednesday alone could start tomorrow.
    expect(submit(week([[6, '17:00', '21:00']]), '2026-10-19')->status)->toBe(ShiftProposalStatus::Submitted);
});

it('approves into a new schedule version and closes the one in force the day before', function () {
    Event::fake([ShiftRulesChanged::class]);
    approve(submit());

    $next = submit(week([[6, '17:00', '22:00'], [2, '16:00', '21:00'], [3, '18:00', '21:00']]), '2026-10-24');
    approve($next);

    $v1 = ShiftSchedule::query()->where('version', 1)->sole();
    expect($v1->effective)->toBe('[2026-10-06,2026-10-24)')
        ->and(ShiftSchedule::inForce($this->teacher->id, CarbonImmutable::parse('2026-10-23'))->version)->toBe(1)
        ->and(ShiftSchedule::inForce($this->teacher->id, CarbonImmutable::parse('2026-10-24'))->shifts()->weeklyMinutes())->toBe(780)
        ->and(ShiftProposal::query()->where('status', 'superseded')->count())->toBe(1);
    Event::assertDispatched(ShiftRulesChanged::class, 2);
});

it('keeps one conversation: changes requested, the teacher edits, a new revision goes back', function () {
    $proposal = submit();
    app(ReviewShiftProposal::class)->requestChanges($proposal, 'ممكن الأربع بدل التلات؟', $this->admin->id);

    $again = submit(week([[6, '17:00', '21:00'], [3, '16:00', '21:00']]));

    expect($again->id)->toBe($proposal->id)
        ->and($again->status)->toBe(ShiftProposalStatus::Submitted)
        ->and($again->revision)->toBe(2)
        ->and($again->shifts(1)->on(2))->toHaveCount(1)
        ->and($again->shifts()->on(3))->toHaveCount(1);
});

it('needs the teacher to accept a counter-offer, unless an admin writes an override reason', function () {
    $proposal = submit();
    app(CounterShiftProposal::class)->handle($this->teacher->id, week([[6, '17:00', '21:00'], [2, '17:00', '21:00']]), CarbonImmutable::parse('2026-10-06'), 'التلات من 5', $this->admin->id);

    expect($proposal->refresh()->status)->toBe(ShiftProposalStatus::Countered)
        ->and($proposal->revision)->toBe(2)
        ->and(fn () => approve($proposal))->toThrow(ValidationException::class);

    expect(approve($proposal, 'teacher')->status)->toBe(ShiftProposalStatus::Approved);
});

it('applies an admin schedule at once in an admin-only academy', function () {
    SchedulingSettings::query()->create(['shift_mode' => 'admin_only']);

    $proposal = app(CounterShiftProposal::class)->handle($this->teacher->id, week(), CarbonImmutable::parse('2026-10-06'), null, $this->admin->id);

    expect($proposal->status)->toBe(ShiftProposalStatus::Approved)
        ->and(ShiftSchedule::inForce($this->teacher->id, CarbonImmutable::parse('2026-10-10')))->not->toBeNull();
});

it('blocks approval while a booked weekly slot falls outside the new shifts, until it is resolved', function () {
    approve(submit());
    $student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Riyadh']);
    $subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
    // Tuesdays 17:00 on the teacher's clock, across Egypt's change of clocks on 29 October.
    foreach (['2026-10-27', '2026-11-03', '2026-11-10'] as $day) {
        app(ScheduleSession::class)->handle(new NewSession($this->teacher->id, $student->id, $subject, CarbonImmutable::parse("{$day} 17:00", 'Africa/Cairo'), 45, 'Africa/Cairo'));
    }

    $proposal = submit(week([[6, '17:00', '21:00'], [3, '16:00', '21:00']]), '2026-10-24');
    expect(fn () => approve($proposal))->toThrow(ValidationException::class);

    $conflicts = app(ShiftImpactQuery::class)->conflicts($this->teacher->id, $proposal->shifts(), $proposal->effective_from);
    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0])->toMatchArray(['weekday' => 2, 'start' => '17:00', 'count' => 3, 'student' => $student->name]);

    app(ResolveShiftConflict::class)->handle($proposal, $conflicts[0]['key'], 'delay', $this->admin->id);
    expect($proposal->refresh()->effective_from->toDateString())->toBe('2026-11-11');

    expect(approve($proposal)->status)->toBe(ShiftProposalStatus::Approved);
});

it('lets an admin approve over unresolved conflicts only with a written reason', function () {
    approve(submit());
    $student = User::factory()->withRole(Role::Student)->create();
    $subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
    app(ScheduleSession::class)->handle(new NewSession($this->teacher->id, $student->id, $subject, CarbonImmutable::parse('2026-10-27 14:00', 'UTC'), 45, 'Africa/Cairo'));

    $proposal = submit(week([[6, '17:00', '21:00']]), '2026-10-24');

    expect(approve($proposal, override: 'الطالب هيتنقل الأسبوع ده')->override_reason)->toBe('الطالب هيتنقل الأسبوع ده');
});

it('allows one open proposal per teacher and lets the teacher withdraw it', function () {
    $proposal = submit();
    app(WithdrawShiftProposal::class)->handle($proposal, $this->teacher->id);

    expect($proposal->refresh()->status)->toBe(ShiftProposalStatus::Withdrawn)
        ->and(submit()->id)->not->toBe($proposal->id)
        ->and(fn () => ShiftProposal::query()->create(['teacher_id' => $this->teacher->id, 'author_role' => 'teacher', 'status' => 'submitted', 'timezone' => 'Africa/Cairo', 'effective_from' => '2026-10-06']))
        ->toThrow(QueryException::class);
});

it('keeps proposals inside their academy', function () {
    submit();
    $this->actAsTenant($this->createAcademy(['slug' => 'fajr']));

    expect(ShiftProposal::query()->count())->toBe(0);
});
