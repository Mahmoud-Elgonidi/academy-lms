<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Actions\ExpireCredits;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Cairo.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Europe/London']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $this->student->id);

    // The teacher works Saturdays and Tuesdays, 17:00–21:00 Cairo.
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '17:00', 'duration_min' => 240]);
    }

    BillingSettings::query()->create(['tax_rate_bp' => 1500, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-']);
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Holds Saturdays and Tuesdays at 18:00 Cairo for eight weeks and enrolls the student on the plan. */
function enrolOnPlan(?string $planId = null): EnrollmentBooked
{
    $t = test();
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse("{$first} 18:00", 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $t->student->id, $times, 30, $t->scheduler->id);

    return app(EnrollStudent::class)->handle($batch, $t->student->id, $t->quran, new EnrollmentTerms($planId ?? $t->plan->id, $t->guardian->id, $t->gulf->id), $t->scheduler->id);
}

/** The teacher and the student join on time, then the sweeper closes the session as completed. */
function teachSession(Session $session): void
{
    CarbonImmutable::setTestNow($session->starts_at);
    app(RecordParticipantJoin::class)->handle($session, $session->teacher_id);
    app(RecordParticipantJoin::class)->handle($session->refresh(), test()->student->id);
    CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();
}

function ledgerAddsUp(string $accountId): void
{
    $account = CreditAccount::query()->findOrFail($accountId);
    expect($account->available_min)->toBe((int) CreditLot::query()->where('account_id', $accountId)->sum('remaining_min'))
        ->and($account->reserved_min)->toBe((int) CreditReservation::query()->where('account_id', $accountId)->where('status', 'held')->sum('minutes'));
}

it('enrolls from a hold: enrollment, weekly times, sessions to the horizon, and the first invoice', function () {
    $booked = enrolOnPlan();

    $enrollment = Enrollment::query()->findOrFail($booked->enrollmentId);
    $invoice = Invoice::query()->findOrFail($booked->invoice->id);

    expect($enrollment->status)->toBe(EnrollmentStatus::PendingPayment)
        ->and($enrollment->credit_account_id)->not->toBeNull()
        ->and(RecurringSlot::query()->where('enrollment_id', $enrollment->id)->pluck('local_start', 'weekday')->map(fn ($t) => substr($t, 0, 5))->sortKeys()->all())->toBe([2 => '18:00', 6 => '18:00'])
        ->and($booked->sessions)->toBe(16)
        ->and(Session::query()->where('enrollment_id', $enrollment->id)->where('funding_status', FundingStatus::Unfunded->value)->count())->toBe(16)
        ->and($invoice->number)->toBe('NR-000001')
        ->and($invoice->status)->toBe('issued')
        ->and($invoice->total_minor)->toBe(40000)
        ->and($invoice->tax_minor)->toBe(5217)          // 15% inside 400.00
        ->and($invoice->subtotal_minor)->toBe(34783)
        ->and($invoice->billingAccount->payer_id)->toBe($this->guardian->id)
        ->and($booked->invoice->total->format('en'))->toBe("SAR\u{00A0}400");
});

it('refuses a plan whose session length or weekly count does not match the chosen times', function () {
    $hourly = Plan::query()->create(['name_ar' => 'ساعة', 'name_en' => 'Hour', 'session_minutes' => 60, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);

    expect(fn () => enrolOnPlan($hourly->id))->toThrow(ValidationException::class)
        ->and(Enrollment::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

it('funds the oldest sessions when the invoice is paid, and starts the enrollment', function () {
    $booked = enrolOnPlan();

    app(RecordPayment::class)->handle($booked->invoice->id, 'bank_transfer', Money::parse('400', 'SAR'), 'TRX-1', CarbonImmutable::now(), null, $this->accountant->id);

    $sessions = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->get();
    $account = CreditAccount::query()->findOrFail(Enrollment::query()->findOrFail($booked->enrollmentId)->credit_account_id);

    expect(Invoice::query()->findOrFail($booked->invoice->id)->status)->toBe('paid')
        ->and(Enrollment::query()->findOrFail($booked->enrollmentId)->status)->toBe(EnrollmentStatus::Active)
        ->and(Subscription::query()->where('enrollment_id', $booked->enrollmentId)->value('block_no'))->toBe(1)
        ->and($sessions->take(8)->every(fn (Session $s) => $s->funding_status === FundingStatus::Funded))->toBeTrue()
        ->and($sessions->skip(8)->every(fn (Session $s) => $s->funding_status === FundingStatus::Unfunded))->toBeTrue()
        ->and([$account->available_min, $account->reserved_min])->toBe([0, 240]);
    ledgerAddsUp($account->id);
});

it('takes partial payments and only grants the minutes once the invoice is settled', function () {
    $booked = enrolOnPlan();
    $pay = fn (string $amount) => app(RecordPayment::class)->handle($booked->invoice->id, 'instapay', Money::parse($amount, 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);

    $pay('150');
    expect(Invoice::query()->findOrFail($booked->invoice->id)->outstanding()->minor)->toBe(25000)
        ->and(CreditLot::query()->count())->toBe(0);

    expect(fn () => $pay('300'))->toThrow(ValidationException::class);
    $pay('250');

    expect(Invoice::query()->findOrFail($booked->invoice->id)->status)->toBe('paid')
        ->and(CreditLot::query()->sum('granted_min'))->toBe(240)
        ->and(fn () => $pay('1'))->toThrow(ValidationException::class);
});

it('uses a credit per lesson taught and sends the renewal invoice when two sessions are left', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $sessions = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->get();

    foreach ($sessions->take(5) as $session) {
        teachSession($session);
    }
    expect(Invoice::query()->count())->toBe(1);

    teachSession($sessions[5]);

    $renewal = Invoice::query()->where('number', 'NR-000002')->firstOrFail();
    $account = CreditAccount::query()->findOrFail(Enrollment::query()->findOrFail($booked->enrollmentId)->credit_account_id);
    expect(Session::query()->where('enrollment_id', $booked->enrollmentId)->where('status', SessionStatus::Completed->value)->count())->toBe(6)
        ->and([$account->available_min, $account->reserved_min, $account->consumed_min])->toBe([0, 60, 180])
        ->and($renewal->status)->toBe('issued')
        ->and($renewal->lines()->first()->meta['block_no'])->toBe(2);

    // A seventh lesson does not send a second renewal while one is waiting.
    teachSession($sessions[6]);
    expect(Invoice::query()->count())->toBe(2);

    // Paying it funds the next eight waiting sessions.
    app(RecordPayment::class)->handle($renewal->id, 'bank_transfer', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    expect(Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', FundingStatus::Funded->value)->count())->toBe(16)
        ->and(Subscription::query()->where('enrollment_id', $booked->enrollmentId)->value('block_no'))->toBe(2);
    ledgerAddsUp($account->id);
});

it('turns a teacher no-show into a makeup lot that regular sessions leave alone', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $first = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->firstOrFail();

    CarbonImmutable::setTestNow($first->starts_at->addMinute());
    app(RecordParticipantJoin::class)->handle($first, $this->student->id);
    CarbonImmutable::setTestNow($first->starts_at->addMinutes(10));
    app(ApplyAttendanceDeadlines::class)->handle();

    $makeup = CreditLot::query()->where('source', 'makeup')->firstOrFail();
    expect($first->refresh()->status)->toBe(SessionStatus::TeacherAbsent)
        ->and([$makeup->granted_min, $makeup->remaining_min])->toBe([30, 30])
        ->and($makeup->expires_at->toDateString())->toBe($first->starts_at->addMinutes(10)->addDays(30)->toDateString())
        ->and(Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', FundingStatus::Funded->value)->count())->toBe(8);
    ledgerAddsUp($makeup->account_id);
});

it('gives the minutes of a cancelled lesson back and funds the next waiting one with them', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $sessions = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->get();

    app(CancelSession::class)->handle($sessions[2], 'academy', 'emergency', null, $this->scheduler->id);

    expect($sessions[8]->refresh()->funding_status)->toBe(FundingStatus::Funded)
        ->and($sessions[9]->refresh()->funding_status)->toBe(FundingStatus::Unfunded)
        ->and(CreditReservation::query()->where('session_id', $sessions[2]->id)->value('status'))->toBe('released');
    ledgerAddsUp(CreditReservation::query()->where('session_id', $sessions[2]->id)->value('account_id'));
});

it('keeps the ledger append-only and every write idempotent', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $session = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    teachSession($session);

    // The sweeper and a late admin decision on the same session consume once.
    app(CreditsApi::class)->consume($session->id, "session:{$session->id}:consume");
    expect(CreditEntry::query()->where('type', 'consume')->count())->toBe(1)
        ->and(fn () => CreditEntry::query()->firstOrFail()->update(['minutes' => 1]))->toThrow(LogicException::class)
        ->and(fn () => DB::table('credit_entries')->update(['minutes' => 1]))->toThrow(QueryException::class);
});

it('lets minutes past their date lapse, with a ledger line', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $accountId = Enrollment::query()->findOrFail($booked->enrollmentId)->credit_account_id;
    // Two lessons are cancelled with nothing waiting to take their minutes back (beyond the funded eight, nothing is left unfunded).
    Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', 'unfunded')->update(['status' => 'cancelled']);
    foreach (Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', 'funded')->orderBy('starts_at')->take(2)->get() as $s) {
        app(CancelSession::class)->handle($s, 'academy', 'emergency', null, $this->scheduler->id);
    }
    expect(CreditAccount::query()->findOrFail($accountId)->available_min)->toBe(60);

    $lapsed = app(ExpireCredits::class)->handle(CarbonImmutable::now()->addDays(43));

    expect($lapsed)->toBe(1)
        ->and(CreditAccount::query()->findOrFail($accountId)->available_min)->toBe(0)
        ->and(CreditEntry::query()->where('type', 'expire')->value('minutes'))->toBe(60);
    ledgerAddsUp($accountId);
});

it('voids an unpaid invoice but never a paid one, and numbers stay gapless', function () {
    $booked = enrolOnPlan();
    app(VoidInvoice::class)->handle($booked->invoice->id, 'wrong plan', $this->accountant->id);
    expect(Invoice::query()->findOrFail($booked->invoice->id)->status)->toBe('void')
        ->and(fn () => app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null))->toThrow(ValidationException::class);
});

it('generates sessions nightly up to the horizon without duplicating any', function () {
    $booked = enrolOnPlan();
    // A week later, Monday afternoon in Cairo: the horizon now reaches 7 December.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:00', 'UTC'));

    $first = app(MaterializeSessions::class)->handle();
    $again = app(MaterializeSessions::class)->handle();

    expect(count($first['created']))->toBe(2)   // one more Tuesday and one more Saturday come into the horizon
        ->and($again['created'])->toBe([])
        ->and(Session::query()->where('enrollment_id', $booked->enrollmentId)->count())->toBe(18);
});

it('leaves makeup minutes out of the renewal mark', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $sessions = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->get();

    // The teacher misses the first lesson: 30 minutes become makeup credit.
    CarbonImmutable::setTestNow($sessions[0]->starts_at->addMinute());
    app(RecordParticipantJoin::class)->handle($sessions[0], $this->student->id);
    CarbonImmutable::setTestNow($sessions[0]->starts_at->addMinutes(10));
    app(ApplyAttendanceDeadlines::class)->handle();

    foreach ($sessions->slice(1, 4) as $session) {
        teachSession($session);
    }
    expect(Invoice::query()->count())->toBe(1);   // 120 paid minutes left, makeup aside

    teachSession($sessions[5]);
    expect(Invoice::query()->count())->toBe(2);   // 60 left: two sessions, the mark
});

it('sends the renewal invoice when a lesson the teacher missed brings the paid minutes to the mark', function () {
    $booked = enrolOnPlan();
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);
    $sessions = Session::query()->where('enrollment_id', $booked->enrollmentId)->orderBy('starts_at')->get();
    foreach ($sessions->take(5) as $session) {
        teachSession($session);
    }
    expect(Invoice::query()->count())->toBe(1);   // three paid lessons left

    // The teacher misses the sixth: it is owed as a makeup, and two paid lessons are left, the mark.
    CarbonImmutable::setTestNow($sessions[5]->starts_at->addMinute());
    app(RecordParticipantJoin::class)->handle($sessions[5], $this->student->id);
    CarbonImmutable::setTestNow($sessions[5]->starts_at->addMinutes(10));
    app(ApplyAttendanceDeadlines::class)->handle();

    expect($sessions[5]->refresh()->status)->toBe(SessionStatus::TeacherAbsent)
        ->and(Invoice::query()->count())->toBe(2)
        ->and(Invoice::query()->where('number', 'NR-000002')->firstOrFail()->lines()->first()->meta['block_no'])->toBe(2);
});
