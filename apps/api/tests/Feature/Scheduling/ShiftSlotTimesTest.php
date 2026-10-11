<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Credits\Models\CreditReservation;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Actions\ShiftSlotTimes;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Moving a teacher's weekly times for a period (docs/architecture/02,
 * section 1, "نقل المواعيد لفترة"): each weekly time ends before the
 * period, a moved copy covers it, the original comes back after; booked
 * lessons move with their credit, all or nothing, and none is made twice.
 * The fixture: Tuesdays and Saturdays at 18:00 Cairo, paid for, the teacher
 * on shift 17:00-21:00 those days.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->slots = RecurringSlot::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('weekday')->get()->all();   // Tuesday, Saturday
    // Lessons by their local start in Cairo.
    $this->cairoTimes = fn () => Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->get()
        ->map(fn (Session $s) => $s->starts_at->setTimezone('Africa/Cairo')->format('D d H:i'))->all();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('moves the weekly times for a period with their lessons and credit, then brings them back, making nothing twice', function () {
    $count = Session::query()->count();
    $moved = app(ShiftSlotTimes::class)->handle($this->slots, CarbonImmutable::parse('2026-10-12', 'Africa/Cairo'), CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'), 60, $this->admin->id);

    expect($moved)->toBe(4)
        ->and(array_slice(($this->cairoTimes)(), 0, 8))->toBe([
            'Tue 06 18:00', 'Sat 10 18:00',
            'Tue 13 19:00', 'Sat 17 19:00', 'Tue 20 19:00', 'Sat 24 19:00',
            'Tue 27 18:00', 'Sat 31 18:00',
        ])
        // Each moved lesson keeps the minutes held for it.
        ->and(CreditReservation::query()->where('status', 'held')->count())->toBe(8)
        ->and(RecurringSlot::query()->where('weekday', 2)->orderBy('effective')->get()->map(fn ($s) => [$s->status, substr($s->local_start, 0, 5), $s->effective])->all())->toBe([
            ['ended', '18:00', '[2026-10-06,2026-10-12)'],
            ['active', '19:00', '[2026-10-12,2026-10-26)'],
            ['active', '18:00', '[2026-10-26,)'],
        ])
        ->and(app(MaterializeSessions::class)->handle()['created'])->toBe([])
        ->and(Session::query()->count())->toBe($count);

    // A week on, the horizon reaches further: only the original time, once per date.
    CarbonImmutable::setTestNow('2026-10-12 07:00 UTC');
    expect(count(app(MaterializeSessions::class)->handle()['created']))->toBe(2)
        ->and(Session::query()->whereNotNull('occurrence_key')->distinct()->count('occurrence_key'))->toBe(Session::query()->count());
});

it('moves nothing when the student is busy at one of the new times', function () {
    $before = ($this->cairoTimes)();
    $slots = RecurringSlot::query()->orderBy('created_at')->get()->map->only(['id', 'status', 'effective'])->all();
    app(ScheduleSession::class)->handle(new NewSession($this->other->id, $this->student->id, $this->quran, CarbonImmutable::parse('2026-10-20 19:00', 'Africa/Cairo'), 30, 'Asia/Riyadh'));

    expect(fn () => app(ShiftSlotTimes::class)->handle($this->slots, CarbonImmutable::parse('2026-10-12', 'Africa/Cairo'), CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'), 60, $this->admin->id))
        ->toThrow(ValidationException::class)
        ->and(($this->cairoTimes)())->toBe($before)
        ->and(RecurringSlot::query()->orderBy('created_at')->get()->map->only(['id', 'status', 'effective'])->all())->toBe($slots);
});

it('moves a lesson past midnight onto the next day, and back after the period', function () {
    ShiftSchedule::query()->where('teacher_id', $this->teacher->id)->firstOrFail()->rules()
        ->create(['teacher_id' => $this->teacher->id, 'weekday' => 7, 'local_start' => '00:00', 'duration_min' => 120]);

    app(ShiftSlotTimes::class)->handle([$this->slots[1]], CarbonImmutable::parse('2026-10-12', 'Africa/Cairo'), CarbonImmutable::parse('2026-10-26', 'Africa/Cairo'), 360, $this->admin->id);

    $saturdays = fn () => collect(($this->cairoTimes)())->filter(fn ($t) => ! str_starts_with($t, 'Tue'))->take(4)->values()->all();
    expect($saturdays())->toBe(['Sat 10 18:00', 'Sun 18 00:00', 'Sun 25 00:00', 'Sat 31 18:00'])
        ->and(RecurringSlot::query()->where('status', 'active')->where('weekday', 7)->value('effective'))->toBe('[2026-10-13,2026-10-27)')
        ->and(app(MaterializeSessions::class)->handle()['created'])->toBe([])
        ->and($saturdays())->toBe(['Sat 10 18:00', 'Sun 18 00:00', 'Sun 25 00:00', 'Sat 31 18:00']);
});

it('lets lessons move into their own old time, and refuses no move or a move of a week', function () {
    expect(app(ShiftSlotTimes::class)->handle([$this->slots[0]], CarbonImmutable::parse('2026-10-12', 'Africa/Cairo'), null, 15, $this->admin->id))->toBe(7)
        ->and(array_slice(($this->cairoTimes)(), 0, 4))->toBe(['Tue 06 18:00', 'Sat 10 18:00', 'Tue 13 18:15', 'Sat 17 18:00'])
        ->and(fn () => app(ShiftSlotTimes::class)->handle([$this->slots[1]], CarbonImmutable::now(), null, 0, $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => app(ShiftSlotTimes::class)->handle([$this->slots[1]], CarbonImmutable::now(), null, 7 * 1440, $this->admin->id))->toThrow(ValidationException::class);
});
