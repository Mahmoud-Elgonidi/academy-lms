<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\AccrueLessonPay;
use Modules\Payroll\Actions\AddPayAdjustment;
use Modules\Payroll\Actions\ClosePayrollMonth;
use Modules\Payroll\Actions\ExportPayrollMonth;
use Modules\Payroll\Actions\RecalculatePayrollMonth;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Filament\Pages\Payroll;
use Modules\Payroll\Filament\Pages\PayrollStatement;
use Modules\Payroll\Filament\PayrollView;
use Modules\Payroll\Filament\Resources\RateCards\ManageRateCards;
use Modules\Payroll\Filament\Teacher\MyEarnings;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Support\PayrollMonths;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Teachers' pay (docs/architecture/01, section 13; 02, "مرتبات المدرسين").
 * The fixture: a London student with a Cairo teacher, Tuesdays and
 * Saturdays 18:00 Cairo for 30 minutes from 6 October 2026, paid; the
 * academy's clock is Cairo. The default card pays 120 EGP an hour.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function payLesson(string $cairoDay): Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse($cairoDay.' 18:00', 'Africa/Cairo')->utc())->firstOrFail();
}

/** Both join on time and stay; the deadlines close it. Teacher null: only the student comes. Student false: nobody comes. */
function teachForPay(string $cairoDay, bool $teacher = true, bool $student = true): Session
{
    $s = payLesson($cairoDay);
    CarbonImmutable::setTestNow($s->starts_at);
    foreach (array_filter([$student ? test()->student->id : null, $teacher ? $s->teacher_id : null]) as $who) {
        app(RecordParticipantJoin::class)->handle($s->refresh(), $who, $s->starts_at);
        app(RecordParticipantLeave::class)->handle($s->refresh(), $who, $s->ends_at);
    }
    CarbonImmutable::setTestNow($s->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();

    return $s->refresh();
}

function payDefaultCard(array $lines = [['unit' => 'hour', 'amount' => '120']], array $teachers = []): RateCard
{
    return app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => $lines] + ($teachers ? ['teachers' => $teachers] : []));
}

function payLines(?string $sessionId = null)
{
    return TeacherEarning::query()->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))->orderBy('created_at')->orderBy('id')->get();
}

it('pays a taught lesson from the teacher\'s card, by the minute, in the month of the lesson on the academy\'s clock', function () {
    payDefaultCard([
        ['unit' => 'hour', 'amount' => '120'],
        ['session_type' => 'trial', 'unit' => 'lesson', 'amount' => '40'],
    ]);

    $lesson = teachForPay('2026-10-06');
    $line = payLines($lesson->id)->sole();

    expect($line->only(['kind', 'teacher_id', 'outcome', 'booked_min', 'pay_pct', 'unit', 'rate_minor', 'amount_minor', 'currency']))->toBe([
        'kind' => 'lesson', 'teacher_id' => $this->teacher->id, 'outcome' => 'completed', 'booked_min' => 30, 'pay_pct' => 100,
        'unit' => 'hour', 'rate_minor' => 12000, 'amount_minor' => 6000, 'currency' => 'EGP',
    ])->and($line->period->format('Y-m-d'))->toBe('2026-10-01')
        ->and($line->lesson_on->format('Y-m-d'))->toBe('2026-10-06');

    // Hearing about the lesson again writes nothing.
    app(AccrueLessonPay::class)->handle($lesson->id);
    expect(payLines()->count())->toBe(1);
});

it('picks the closest price: subject and kind, then subject, then kind, then any lesson', function () {
    $other = app(CatalogApi::class)->createSubject('نحو', 'Grammar');
    $card = payDefaultCard([
        ['unit' => 'hour', 'amount' => '100'],
        ['subject_id' => $this->quran, 'unit' => 'hour', 'amount' => '150'],
        ['session_type' => 'regular', 'unit' => 'hour', 'amount' => '110'],
        ['subject_id' => $other, 'session_type' => 'regular', 'unit' => 'lesson', 'amount' => '90'],
    ]);

    expect(payLines(teachForPay('2026-10-06')->id)->sole()->amount_minor)->toBe(7500)       // Quran beats "regular lessons"
        ->and(fn () => app(SaveRateCard::class)->handle($card, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [
            ['unit' => 'hour', 'amount' => '100'], ['unit' => 'lesson', 'amount' => '50'],
        ]]))->toThrow(ValidationException::class);
});

it('pays a share of a cancelled lesson as the policy decided: half for a late cancellation by the student, nothing for an early one', function () {
    payDefaultCard();
    $late = payLesson('2026-10-06');
    $early = payLesson('2026-10-10');

    CarbonImmutable::setTestNow($late->starts_at->subHours(3));
    app(CancelSession::class)->handle($late, 'student', 'illness', null, $this->student->id);
    app(CancelSession::class)->handle($early, 'student', 'travel', null, $this->student->id);

    expect(payLines($late->id)->sole()->only(['outcome', 'rule', 'pay_pct', 'amount_minor']))->toBe(['outcome' => 'cancelled', 'rule' => 4, 'pay_pct' => 50, 'amount_minor' => 3000])
        ->and(payLines($early->id))->toHaveCount(0);
});

it('pays nothing when the teacher did not come, and pays the student\'s absence as the policy says', function () {
    payDefaultCard();

    $absent = teachForPay('2026-10-06', teacher: false);
    expect($absent->status->value)->toBe('teacher_absent')->and(payLines($absent->id))->toHaveCount(0);

    $s = payLesson('2026-10-10');
    CarbonImmutable::setTestNow($s->starts_at);
    app(RecordParticipantJoin::class)->handle($s, $this->teacher->id, $s->starts_at);
    CarbonImmutable::setTestNow($s->starts_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();

    expect($s->refresh()->status->value)->toBe('student_absent')
        ->and(payLines($s->id)->sole()->only(['outcome', 'pay_pct', 'amount_minor']))->toBe(['outcome' => 'student_absent', 'pay_pct' => 100, 'amount_minor' => 6000]);
});

it('pays whoever taught: a substitute is paid from their own card', function () {
    payDefaultCard();
    app(SaveRateCard::class)->handle(null, ['name' => 'Gulf', 'currency' => 'SAR', 'lines' => [['unit' => 'lesson', 'amount' => '35']], 'teachers' => [$this->other->id]]);

    $lesson = payLesson('2026-10-06');
    app(SubstituteTeacher::class)->handle($lesson, $this->other->id, $this->admin->id);
    $lesson = teachForPay('2026-10-06');

    expect(payLines($lesson->id)->sole()->only(['teacher_id', 'unit', 'amount_minor', 'currency']))->toBe(['teacher_id' => $this->other->id, 'unit' => 'lesson', 'amount_minor' => 3500, 'currency' => 'SAR']);
});

it('follows a lesson through review: no pay while it is under review, then what the manager decided', function () {
    payDefaultCard();
    $first = teachForPay('2026-10-06', teacher: false, student: false);
    expect($first->status->value)->toBe('needs_review')->and(payLines($first->id))->toHaveCount(0);

    app(ResolveReview::class)->handle($first, 'completed', 'They met on the phone.', $this->admin->id);
    expect(payLines($first->id)->sole()->amount_minor)->toBe(6000);

    $second = teachForPay('2026-10-10', teacher: false, student: false);
    app(ResolveReview::class)->handle($second, 'teacher_absent', null, $this->admin->id);
    expect(payLines($second->id))->toHaveCount(0)
        ->and($second->refresh()->decision['teacher_pay_pct'])->toBe(0);
});

it('keeps the price a lesson was paid at; recalculating an open month reprices it with a correction', function () {
    $card = payDefaultCard();
    $lesson = teachForPay('2026-10-06');

    app(SaveRateCard::class)->handle($card, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [['unit' => 'hour', 'amount' => '150']]]);
    app(AccrueLessonPay::class)->handle($lesson->id);
    expect(payLines($lesson->id)->sole()->amount_minor)->toBe(6000);

    $changed = app(RecalculatePayrollMonth::class)->handle(CarbonImmutable::parse('2026-10-01'), reprice: true, actorId: $this->accountant->id);
    $all = payLines($lesson->id);

    expect($changed)->toBe(1)
        ->and($all->pluck('kind')->all())->toBe(['lesson', 'reversal', 'lesson'])
        ->and($all->pluck('amount_minor')->all())->toBe([6000, -6000, 7500])
        ->and($all[1]->reverses_id)->toBe($all[0]->id)
        ->and(app(PayrollMonth::class)->teachers(CarbonImmutable::parse('2026-10-01'))[0]['total']->minor)->toBe(7500)
        ->and(app(PayrollMonth::class)->statement(CarbonImmutable::parse('2026-10-01'), $this->teacher->id)['lessons'])->toHaveCount(1);
});

it('shows lessons with no price, and prices them once the card has one', function () {
    $lesson = teachForPay('2026-10-06');
    $october = CarbonImmutable::parse('2026-10-01');

    expect(payLines())->toHaveCount(0)
        ->and(app(PayrollMonth::class)->unpriced($october))->toBe([$this->teacher->id => 1]);

    payDefaultCard();
    expect(app(RecalculatePayrollMonth::class)->handle($october, reprice: false, actorId: $this->accountant->id))->toBe(1)
        ->and(payLines($lesson->id)->sole()->amount_minor)->toBe(6000)
        ->and(app(PayrollMonth::class)->unpriced($october))->toBe([]);
});

it('takes bonuses and deductions with a reason, in the teacher\'s currency, and takes them back with a reversal', function () {
    payDefaultCard();
    teachForPay('2026-10-06');
    $add = app(AddPayAdjustment::class);

    $bonus = $add->handle($this->teacher->id, 'bonus', '250', 'Covered two extra lessons', $this->accountant->id);
    $deduction = $add->handle($this->teacher->id, 'deduction', '40.5', 'Report missing twice', $this->accountant->id);

    expect([$bonus->amount_minor, $bonus->currency, $deduction->amount_minor])->toBe([25000, 'EGP', -4050])
        ->and(fn () => $add->handle($this->teacher->id, 'bonus', '10', '  ', $this->accountant->id))->toThrow(ValidationException::class)
        ->and(fn () => $add->handle($this->teacher->id, 'bonus', '0', 'Nothing', $this->accountant->id))->toThrow(ValidationException::class)
        ->and(fn () => $add->handle($this->student->id, 'bonus', '10', 'Not a teacher', $this->accountant->id))->toThrow(ValidationException::class);

    $add->reverse($deduction->id, $this->accountant->id);
    expect(fn () => $add->reverse($deduction->id, $this->accountant->id))->toThrow(ValidationException::class);

    $row = app(PayrollMonth::class)->teachers(CarbonImmutable::parse('2026-10-01'))[0];
    expect([$row['lessons'], $row['lesson_pay']->minor, $row['bonuses']->minor, $row['deductions']->minor, $row['total']->minor])->toBe([1, 6000, 25000, 0, 31000]);
});

it('closes a month once it is over, in order; a closed month takes no more lines and later corrections go to the next one', function () {
    payDefaultCard();
    $september = CarbonImmutable::parse('2026-09-01');
    $october = CarbonImmutable::parse('2026-10-01');
    $close = app(ClosePayrollMonth::class);
    $add = app(AddPayAdjustment::class);

    $taughtLesson = teachForPay('2026-10-06');
    $review = teachForPay('2026-10-10', teacher: false, student: false);
    $add->handle($this->teacher->id, 'bonus', '100', 'Welcome bonus', $this->accountant->id, $september);

    expect(fn () => $close->handle($october, $this->accountant->id))->toThrow(ValidationException::class);     // October is not over

    CarbonImmutable::setTestNow('2026-11-02 09:00 UTC');
    expect(fn () => $close->handle($october, $this->accountant->id))->toThrow(ValidationException::class);     // September first
    $close->handle($september, $this->accountant->id);
    $close->handle($october, $this->accountant->id);
    expect(fn () => $close->handle($october, $this->accountant->id))->toThrow(ValidationException::class);

    // The October lesson under review is decided in November: paid in November.
    app(ResolveReview::class)->handle($review->refresh(), 'completed', null, $this->admin->id);
    expect(payLines($review->id)->sole()->period->format('Y-m'))->toBe('2026-11');

    // A correction to an October line lands in November; October stays as it was.
    app(SaveRateCard::class)->handle(RateCard::query()->sole(), ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [['unit' => 'hour', 'amount' => '150']]]);
    expect(fn () => app(RecalculatePayrollMonth::class)->handle($october, reprice: true, actorId: $this->accountant->id))->toThrow(ValidationException::class);
    $add->handle($this->teacher->id, 'deduction', '10', 'Late twice', $this->accountant->id, $october);
    expect(TeacherEarning::query()->where('kind', 'deduction')->value('period')->format('Y-m'))->toBe('2026-11')
        ->and(app(PayrollMonth::class)->teachers($october)[0]['total']->minor)->toBe(6000);

    // The database holds it too.
    expect(fn () => TeacherEarning::query()->create(['teacher_id' => $this->teacher->id, 'period' => $october, 'kind' => 'bonus', 'amount_minor' => 1, 'currency' => 'EGP', 'note' => 'x']))
        ->toThrow(QueryException::class, 'payroll month 2026-10 is closed')
        ->and(fn () => DB::table('teacher_earnings')->where('session_id', $taughtLesson->id)->update(['amount_minor' => 1]))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::table('teacher_earnings')->where('session_id', $taughtLesson->id)->delete())->toThrow(QueryException::class, 'append-only');
});

it('tells each teacher their total when the month is closed', function () {
    payDefaultCard();
    teachForPay('2026-10-06');
    teachForPay('2026-10-10');

    CarbonImmutable::setTestNow('2026-11-01 10:00 UTC');
    app(ClosePayrollMonth::class)->handle(CarbonImmutable::parse('2026-10-01'), $this->accountant->id);

    $sent = Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'payroll.month_closed')->sole();
    expect($sent->title)->toContain('أكتوبر 2026')
        ->and($sent->body)->toContain('120')->toContain('2')
        ->and(Notification::query()->where('user_id', $this->other->id)->where('template_key', 'payroll.month_closed')->count())->toBe(0);
});

it('exports the month as a spreadsheet: totals, lessons, bonuses and deductions', function () {
    payDefaultCard();
    teachForPay('2026-10-06');
    app(AddPayAdjustment::class)->handle($this->teacher->id, 'bonus', '50', 'Great reports', $this->accountant->id);

    app()->setLocale('en');
    $path = app(ExportPayrollMonth::class)->handle(CarbonImmutable::parse('2026-10-01'));
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    expect(array_keys($sheets))->toBe(['Summary', 'Lessons', 'Bonuses and deductions'])
        ->and($sheets['Summary'][2])->toBe(['Teacher', 'Currency', 'Lessons', 'Hours', 'Lesson pay', 'Bonuses', 'Deductions', 'Total'])
        ->and(array_slice($sheets['Summary'][3], 1))->toEqual(['EGP', 1, 0.5, 60, 50, 0, 110])
        ->and(array_slice($sheets['Lessons'][1], 1, 13))->toEqual(['2026-10-06', '18:00', $this->student->name, 'Quran', 'Regular', 'Taught', 30, 1, 120, 'hour', 60, 'EGP', ''])
        ->and(array_slice($sheets['Bonuses and deductions'][1], 1, 4))->toEqual(['Bonus', 'Great reports', 50, 'EGP']);
});

it('shows the month to whoever runs payroll, with each teacher\'s total, and lets them add a bonus and close the month', function () {
    payDefaultCard();
    teachForPay('2026-10-06');
    teachForPay('2026-10-10');
    CarbonImmutable::setTestNow('2026-11-03 09:00 UTC');

    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Livewire::withQueryParams(['month' => '2026-10'])->test(Payroll::class)
        ->assertSee($this->teacher->name)->assertSee(PayrollMonths::label(CarbonImmutable::parse('2026-10-01')))->assertSee(Money::of(12000, 'EGP')->format())
        ->assertDontSee($this->other->name)
        ->callAction('adjust', ['teacher' => $this->teacher->id, 'kind' => 'bonus', 'amount' => '75', 'note' => 'Covered a colleague'])
        ->assertHasNoActionErrors()
        ->assertSee(Money::of(19500, 'EGP')->format())
        ->callAction('close')
        ->assertActionHidden('close')
        ->assertSee($this->accountant->name);

    expect(app(PayrollMonth::class)->state(CarbonImmutable::parse('2026-10-01'))['closed'])->toBeTrue();

    Livewire::withQueryParams(['month' => '2026-10'])->test(PayrollStatement::class, ['teacher' => $this->teacher->id])
        ->assertSee($this->student->name)->assertSee('Covered a colleague')->assertSee(__('payroll::payroll.share', ['min' => 30, 'pct' => PayrollView::pct(100)]));
});

it('downloads the month\'s spreadsheet from the payroll screen', function () {
    payDefaultCard();
    teachForPay('2026-10-06');

    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Livewire::withQueryParams(['month' => '2026-10'])->test(Payroll::class)
        ->callAction('export')
        ->assertFileDownloaded('payroll-2026-10.xlsx');
});

it('keeps rate cards on their own screen: one default, a teacher on one card at a time', function () {
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');

    Livewire::test(ManageRateCards::class)
        ->callAction('create', ['name' => 'Standard', 'currency' => 'EGP', 'is_default' => true, 'lines' => [['subject_id' => null, 'session_type' => null, 'unit' => 'hour', 'amount' => '120']], 'teachers' => []])
        ->assertHasNoActionErrors()
        ->callAction('create', ['name' => 'Gulf', 'currency' => 'SAR', 'is_default' => false, 'lines' => [['subject_id' => $this->quran, 'session_type' => 'trial', 'unit' => 'lesson', 'amount' => '30']], 'teachers' => [$this->other->id]])
        ->assertHasNoActionErrors()
        ->assertSee('Standard')->assertSee('Gulf');

    expect(RateCard::forTeacher($this->teacher->id)->name)->toBe('Standard')
        ->and(RateCard::forTeacher($this->other->id)->name)->toBe('Gulf')
        ->and(RateCard::query()->where('is_default', true)->count())->toBe(1);
});

it('shows a teacher their own earnings and rates, never anyone else\'s; families and other staff do not see payroll', function () {
    payDefaultCard();
    teachForPay('2026-10-06');
    app(AddPayAdjustment::class)->handle($this->other->id, 'bonus', '500', 'Secret bonus', $this->accountant->id);
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::withQueryParams(['month' => '2026-10'])->test(MyEarnings::class)
        ->assertSee(Money::of(6000, 'EGP')->format())->assertSee($this->student->name)
        ->assertSee(__('payroll::payroll.units.hour', ['amount' => Money::of(12000, 'EGP')->format()]))
        ->assertDontSee('Secret bonus');

    // A new person each time: their own session, as in a browser.
    $as = fn (User $user) => $this->flushSession()->actingAs($user);
    $as($this->teacher)->get('http://noor.lms.test/teach/earnings')->assertOk();
    $as($this->teacher)->get('http://noor.lms.test/admin/payroll')->assertForbidden();
    $as($supervisor)->get('http://noor.lms.test/admin/payroll')->assertForbidden();
    $as($this->admin)->get('http://noor.lms.test/admin/payroll')->assertForbidden();     // owner and accountant only (docs/architecture/01, permissions)
    $as($this->accountant)->get('http://noor.lms.test/admin/payroll/'.$this->teacher->id)->assertOk();
    $as($this->accountant)->get('http://noor.lms.test/admin/payroll/'.$this->student->id)->assertNotFound();
    $as($this->accountant)->get('http://noor.lms.test/admin/settings/teacher-rates')->assertOk();
    $as($guardian)->get('http://noor.lms.test/teach/earnings')->assertForbidden();
});

it('prices the month\'s unpriced lessons from the board, and takes a bonus back from the statement', function () {
    teachForPay('2026-10-06');
    payDefaultCard();
    $bonus = app(AddPayAdjustment::class)->handle($this->teacher->id, 'bonus', '30', 'Typo bonus', $this->accountant->id);

    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Livewire::withQueryParams(['month' => '2026-10'])->test(Payroll::class)
        ->assertSee(trans_choice('payroll::payroll.unpriced.text', 1, ['count' => 1]))
        ->call('priceMissing')
        ->assertDontSee(trans_choice('payroll::payroll.unpriced.text', 1, ['count' => 1]));
    expect(payLines()->where('kind', 'lesson')->sole()->amount_minor)->toBe(6000);

    Livewire::withQueryParams(['month' => '2026-10'])->test(PayrollStatement::class, ['teacher' => $this->teacher->id])
        ->callAction(TestAction::make('reverse')->arguments(['entry' => $bonus->id]))
        ->assertSee(__('payroll::payroll.statement.reversed_tag'));
    expect(app(PayrollMonth::class)->teachers(CarbonImmutable::parse('2026-10-01'))[0]['total']->minor)->toBe(6000);
});
