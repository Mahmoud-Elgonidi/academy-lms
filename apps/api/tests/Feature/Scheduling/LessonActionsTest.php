<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Credits\Models\CreditReservation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\CreateRecurringSlots;
use Modules\Scheduling\Actions\FlagForReview;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordConnectionDrop;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\TakeHeldBatch;
use Modules\Scheduling\Contracts\Events\SessionFlaggedForReview;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\LiveBoard\BoardClassifier;
use Modules\Scheduling\Domain\LiveBoard\BoardIssue;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Queries\LiveBoardQuery;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The smaller steps of a lesson's life, each on its own: sending a lesson to
 * review, a dropped connection, taking a coordinator's hold, and booking
 * weekly times. The fixture: Monday 5 October 2026, Tuesdays and Saturdays
 * at 18:00 Cairo (the first, Tuesday 6 October 15:00-15:30 UTC).
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('sends a lesson to review for a known reason, its minutes still held, and says so once', function () {
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    CarbonImmutable::setTestNow('2026-10-06 15:00 UTC');
    app(RecordParticipantJoin::class)->handle($this->lesson->refresh(), $this->teacher->id);
    Event::fake([SessionFlaggedForReview::class]);

    expect(fn () => app(FlagForReview::class)->handle($this->lesson->refresh(), 'the_weather', null, $this->admin->id))->toThrow(ValidationException::class);
    app(FlagForReview::class)->handle($this->lesson, 'technical', 'Both lost sound', $this->admin->id);

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::NeedsReview)
        ->and($this->lesson->review_reason)->toBe('technical')
        ->and(CreditReservation::query()->where('session_id', $this->lesson->id)->value('status'))->toBe('held')
        ->and($this->lesson->events()->where('kind', 'status_changed')->get()->last()->data)->toMatchArray(['from' => 'in_progress', 'to' => 'needs_review', 'reason' => 'technical', 'note' => 'Both lost sound'])
        // Already under review, or cancelled: nothing to send.
        ->and(fn () => app(FlagForReview::class)->handle($this->lesson, 'other', null, $this->admin->id))->toThrow(ValidationException::class);
    Event::assertDispatchedTimes(SessionFlaggedForReview::class, 1);
    Event::assertDispatched(SessionFlaggedForReview::class, fn ($e) => $e->sessionId === $this->lesson->id && $e->reason === 'technical');

    $second = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();
    app(CancelSession::class)->handle($second, 'academy', 'closure', null, $this->admin->id);
    expect(fn () => app(FlagForReview::class)->handle($second->refresh(), 'other', null, $this->admin->id))->toThrow(ValidationException::class);
});

it('shows a weak connection on the board after three drops within fifteen minutes, not two or three spread out', function () {
    CarbonImmutable::setTestNow('2026-10-06 15:00 UTC');
    app(RecordParticipantJoin::class)->handle($this->lesson->refresh(), $this->teacher->id);
    app(RecordParticipantJoin::class)->handle($this->lesson->refresh(), $this->student->id);
    $issueAt = function (string $now) {
        $at = CarbonImmutable::parse("2026-10-06 {$now}", 'UTC');
        $items = (new BoardClassifier(AttendancePolicy::defaults()))->classify(app(LiveBoardQuery::class)->around($at, 60, 'Africa/Cairo'), $at);

        return collect($items)->flatten(1)->first(fn ($i) => $i->session->id === $this->lesson->id)?->issue;
    };
    $drop = fn (string $at) => app(RecordConnectionDrop::class)->handle($this->lesson, $this->student->id, 20, CarbonImmutable::parse("2026-10-06 {$at}", 'UTC'));

    $drop('15:01');
    $drop('15:09');
    expect($issueAt('15:10'))->toBeNull();

    $drop('15:17');     // three, but the first is more than fifteen minutes before the last
    expect($issueAt('15:17'))->toBeNull()
        ->and($this->lesson->events()->where('kind', 'connection_drop')->count())->toBe(3);

    $drop('15:20');
    expect($issueAt('15:20'))->toBe(BoardIssue::WeakConnection)
        ->and($this->lesson->events()->where('kind', 'connection_drop')->get()->last()->data)->toEqual(['user_id' => $this->student->id, 'seconds' => 20]);
});

it('takes a coordinator\'s own live hold, in date order, and nobody else\'s or an expired one', function () {
    $coordinator = User::factory()->withRole(Role::Scheduler)->create();
    $later = CarbonImmutable::parse('2026-10-13 19:00', 'Africa/Cairo')->getTimestamp();
    $sooner = CarbonImmutable::parse('2026-10-10 19:00', 'Africa/Cairo')->getTimestamp();
    $batch = app(PlaceBookingHold::class)->handle($this->teacher->id, $this->student->id, [$later, $sooner], 30, $coordinator->id);

    expect(app(TakeHeldBatch::class)->handle($batch, $coordinator->id)->map(fn ($h) => $h->starts_at->getTimestamp())->all())->toBe([$sooner, $later])
        ->and(fn () => app(TakeHeldBatch::class)->handle($batch, $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => app(TakeHeldBatch::class)->handle('01a12c00-0000-7000-8000-000000000000', $coordinator->id))->toThrow(ValidationException::class);

    // Ten minutes is the hold's life: at 10:00 sharp it is still there, a second later it is gone.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(10));
    expect(app(TakeHeldBatch::class)->handle($batch, $coordinator->id))->toHaveCount(2);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());
    expect(fn () => app(TakeHeldBatch::class)->handle($batch, $coordinator->id))->toThrow(ValidationException::class, __('scheduling::find.errors.hold_expired'));
});

it('books weekly times on the teacher\'s clock, all or none, never over another live one of the teacher', function () {
    $book = fn (array $times, ?string $teacherId = null) => app(CreateRecurringSlots::class)
        ->handle($this->booked->enrollmentId, $teacherId ?? $this->teacher->id, $this->student->id, 'Africa/Cairo', $times, $this->admin->id);
    $count = RecurringSlot::query()->count();
    $from = CarbonImmutable::parse('2026-10-05');

    // Monday is free, Tuesday 18:15 is inside the 18:00 lesson: neither is booked.
    expect(fn () => $book([['time' => WeeklyTime::at(1, '18:00', 30), 'from' => $from], ['time' => WeeklyTime::at(2, '18:15', 30), 'from' => $from]]))
        ->toThrow(ValidationException::class, __('scheduling::find.errors.slot_taken'))
        ->and(RecurringSlot::query()->count())->toBe($count);

    // A Tuesday 18:00 that ended before this one began, another teacher's Tuesday 18:00, and Sunday 23:45 over midnight: all fine.
    $made = $book([
        ['time' => WeeklyTime::at(2, '18:00', 30), 'from' => CarbonImmutable::parse('2026-08-04'), 'until' => CarbonImmutable::parse('2026-10-06')],
        ['time' => WeeklyTime::at(7, '23:45', 30), 'from' => $from],
    ]);
    $book([['time' => WeeklyTime::at(2, '18:00', 30), 'from' => $from]], $this->other->id);

    expect(collect($made)->map(fn (RecurringSlot $s) => [$s->weekday, substr($s->local_start, 0, 5), $s->effective])->all())->toBe([
        [2, '18:00', '[2026-08-04,2026-10-06)'],
        [7, '23:45', '[2026-10-05,)'],
    ])
        ->and(RecurringSlot::query()->whereKey($made[1]->id)->value('minute_of_week'))->toBe('{[0,15),[10065,10080)}')
        // Monday 00:00 meets the end of Sunday's lesson that runs past midnight.
        ->and(fn () => $book([['time' => WeeklyTime::at(1, '00:00', 30), 'from' => $from]]))->toThrow(ValidationException::class);
});
