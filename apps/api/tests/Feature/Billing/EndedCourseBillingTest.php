<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Discounts\SiblingRank;
use Modules\Billing\Filament\Family\FamilyAccount;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;

/*
 * A course's money when it is paused or ends (docs/architecture/02, section
 * 3, "إيقاف الكورس وإنهاؤه"): its waiting block invoice, a family invoice it
 * shares, a shared package, the family discount, the balance the family sees.
 * Monday 5 October 2026 in Cairo; three children of one guardian.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    $this->kids = User::factory()->withRole(Role::Student)->count(2)->sequence(['name' => 'Ziad'], ['name' => 'Maryam'])->create(['timezone' => 'Africa/Cairo']);
    foreach ($this->kids as $kid) {
        app(PeopleApi::class)->linkGuardian($this->guardian->id, $kid->id);
    }
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '15:00', 'duration_min' => 360]);
    }
    BillingSettings::query()->create(['tax_rate_bp' => 0, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-']);
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    $this->package = Plan::query()->create(['name_ar' => 'باقة 12', 'name_en' => '12 lessons, any subject', 'session_minutes' => 30, 'sessions_per_block' => 12,
        'sessions_per_week' => 3, 'validity_days' => 42, 'credit_scope' => Plan::SHARED]);
    $this->package->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 54000, 'currency' => 'SAR']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function endedEnrol(int $kid, string $subjectId, ?string $planId = null, int $hour = 16, array $firsts = ['2026-10-06', '2026-10-10']): EnrollmentBooked
{
    $t = test();
    $times = [];
    foreach ($firsts as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse(sprintf('%s %02d:00', $first, $hour), 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $t->kids[$kid]->id, $times, 30, $t->scheduler->id);

    return app(EnrollStudent::class)->handle($batch, $t->kids[$kid]->id, $subjectId, new EnrollmentTerms($planId ?? $t->plan->id, $t->guardian->id, $t->gulf->id), $t->scheduler->id);
}

function endedPay(string $invoiceId, ?Money $amount = null): Invoice
{
    $invoice = Invoice::query()->findOrFail($invoiceId);

    return app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', $amount ?? $invoice->outstanding(), 'T-1', CarbonImmutable::now(), null, null);
}

function endedUse(string $enrollmentId, int $count): void
{
    Session::query()->where('enrollment_id', $enrollmentId)->where('funding_status', FundingStatus::Funded->value)
        ->whereExists(fn ($q) => $q->from('credit_reservations as r')->whereColumn('r.session_id', 'sessions.id')->where('r.status', 'held'))
        ->orderBy('starts_at')->limit($count)->get()
        ->each(fn (Session $s) => app(CreditsApi::class)->consume($s->id, "session:{$s->id}:consume"));
}

function endedSubscription(EnrollmentBooked $booked): Subscription
{
    return Subscription::query()->with('plan')->where('enrollment_id', $booked->enrollmentId)->firstOrFail();
}

it('voids a family invoice waiting for a course that ends, and issues the other child\'s block again on its own', function () {
    $ziad = endedEnrol(0, $this->quran, hour: 16);
    $maryam = endedEnrol(1, $this->quran, hour: 17);
    endedPay($ziad->invoice->id);
    endedPay($maryam->invoice->id);
    endedUse($maryam->enrollmentId, 4);
    endedUse($ziad->enrollmentId, 6);
    $family = Invoice::query()->latest('number')->firstOrFail();
    expect($family->subscriptionIds())->toHaveCount(2);

    $done = app(EndEnrollment::class)->handle($maryam->enrollmentId, '2026-10-05', 'Moving school', $this->scheduler->id);

    $again = Invoice::query()->with('lines')->latest('number')->firstOrFail();
    expect($done['billing']->outcome)->toBe('reissued')
        ->and($done['billing']->invoiceNumber)->toBe($family->number)
        ->and($done['billing']->reissuedNumber)->toBe($again->number)
        ->and($family->refresh()->status)->toBe('void')
        ->and($family->void_reason)->toBe(__('billing::billing.void_course_ended'))
        ->and($again->status)->toBe('issued')
        ->and($again->subscriptionIds())->toBe([endedSubscription($ziad)->id])
        ->and((int) $again->lines->where('kind', 'block')->sole()->meta['block_no'])->toBe(2)
        // Maryam's never renews again; Ziad's goes on.
        ->and(Subscription::query()->taught()->pluck('id')->all())->toBe([endedSubscription($ziad)->id]);
});

it('keeps a waiting block with money on it, says why, and paying the rest adds its minutes to the ended course without starting it', function () {
    $ziad = endedEnrol(0, $this->quran);
    endedPay($ziad->invoice->id, Money::parse('100', 'SAR'));

    expect(app(EndEnrollment::class)->preview($ziad->enrollmentId, '2026-10-05')['billing']->outcome)->toBe('kept');
    $done = app(EndEnrollment::class)->handle($ziad->enrollmentId, '2026-10-05', 'Changed mind', $this->scheduler->id);

    expect($done['billing']->outcome)->toBe('kept')
        ->and($done['billing']->why)->toBe('paid')
        ->and(Invoice::query()->find($ziad->invoice->id)->status)->toBe('issued');

    endedPay($ziad->invoice->id);
    $balance = app(CreditsApi::class)->balance(Enrollment::query()->find($ziad->enrollmentId)->credit_account_id);
    expect(Enrollment::query()->find($ziad->enrollmentId)->status)->toBe(EnrollmentStatus::Ended)
        ->and($balance->remainingMin())->toBe(240)
        // The family sees the minutes left on the ended course (a refund is the academy's to make).
        ->and(collect(FamilyAccount::balances($this->kids[0]->id))->sole())->toMatchArray(['status' => 'ended', 'minutes' => 240]);
});

it('ends one course of a shared package: the package and its invoice go on for the other', function () {
    $quran = endedEnrol(0, $this->quran, $this->package->id, hour: 16);
    $arabic = endedEnrol(0, $this->arabic, $this->package->id, hour: 19, firsts: ['2026-10-10']);

    expect(app(EndEnrollment::class)->preview($quran->enrollmentId, '2026-10-05')['billing']->outcome)->toBe('shared');
    $done = app(EndEnrollment::class)->handle($quran->enrollmentId, '2026-10-05', 'Quran elsewhere', $this->scheduler->id);

    expect($done['billing']->outcome)->toBe('shared')
        ->and(Invoice::query()->find($quran->invoice->id)->status)->toBe('issued')
        ->and(Enrollment::query()->find($arabic->enrollmentId)->status)->toBe(EnrollmentStatus::PendingPayment);

    // Paid, the package starts its other course, not the ended one, and goes on renewing for it.
    endedPay($quran->invoice->id);
    expect(Enrollment::query()->find($arabic->enrollmentId)->status)->toBe(EnrollmentStatus::Active)
        ->and(Enrollment::query()->find($quran->enrollmentId)->status)->toBe(EnrollmentStatus::Ended)
        ->and(Subscription::query()->taught()->count())->toBe(1)
        ->and(collect(FamilyAccount::balances($this->kids[0]->id))->sole())->toMatchArray(['status' => 'active', 'subject' => 'Arabic'])
        // The child still counts for the family discount while one of the package's courses is on.
        ->and(app(SiblingRank::class)->of(endedSubscription($quran)->billing_account_id, $this->kids[0]->id))->toBe(1);

    // The last course on it ends: its waiting renewal goes too.
    $renewal = app(IssueRenewal::class)->handle(endedSubscription($quran)->refresh());
    expect($renewal)->not->toBeNull()
        ->and($renewal->id)->not->toBe($quran->invoice->id);
    app(EndEnrollment::class)->handle($arabic->enrollmentId, '2026-10-05', 'Done', $this->scheduler->id);
    expect($renewal->refresh()->status)->toBe('void');
});

it('counts a paused child for the family discount and not an ended one, and shows the family the pause', function () {
    app(SaveSiblingDiscount::class)->handle(10, 15);
    $ziad = endedEnrol(0, $this->quran, hour: 16);
    $maryam = endedEnrol(1, $this->quran, hour: 17);
    endedPay($ziad->invoice->id);
    endedPay($maryam->invoice->id);
    $account = endedSubscription($ziad)->billing_account_id;
    expect(app(RevenueBoard::class)->forecast('SAR')['count'])->toBe(2);

    app(PauseEnrollment::class)->handle($ziad->enrollmentId, '2026-10-05', '2026-10-20', 'Travel', $this->scheduler->id);
    // A paused course is not expected to renew; it still counts for the family discount.
    expect(app(RevenueBoard::class)->forecast('SAR')['count'])->toBe(1)
        ->and(app(SiblingRank::class)->of($account, $this->kids[1]->id))->toBe(2);
    app()->setLocale('en');
    expect(collect(FamilyAccount::balances($this->kids[0]->id, $this->guardian->id))->sole())
        ->toMatchArray(['status' => 'paused', 'extra' => null])
        ->and(collect(FamilyAccount::balances($this->kids[0]->id))->sole()['paused_until']->toDateString())->toBe('2026-10-20');

    app(EndEnrollment::class)->handle($ziad->enrollmentId, '2026-10-05', 'Done', $this->scheduler->id);
    expect(app(SiblingRank::class)->of($account, $this->kids[1]->id))->toBe(1)
        // Ziad's minutes stay (his course is over): the family sees them.
        ->and(collect(FamilyAccount::balances($this->kids[0]->id))->sole()['status'])->toBe('ended');

    // A renewal for Maryam now comes without the family discount: she is the only child on.
    endedUse($maryam->enrollmentId, 6);
    $renewal = Invoice::query()->with('lines')->latest('number')->firstOrFail();
    expect($renewal->subscriptionIds())->toBe([endedSubscription($maryam)->id])
        ->and($renewal->lines->where('kind', 'discount'))->toHaveCount(0)
        ->and(app(IssueRenewal::class)->handle(endedSubscription($ziad)))->toBeNull()
        ->and(DB::table('invoices')->where('status', 'issued')->count())->toBe(1);
});

it('voids the course\'s waiting extra lessons invoice when it ends, unless money may be on its way', function () {
    $ziad = endedEnrol(0, $this->quran, hour: 16);
    $maryam = endedEnrol(1, $this->quran, hour: 17);
    endedPay($ziad->invoice->id);
    endedPay($maryam->invoice->id);
    $extra = app(BuyExtraLessons::class)->handle(endedSubscription($ziad)->id, 2, $this->scheduler->id);
    $started = app(BuyExtraLessons::class)->handle(endedSubscription($maryam)->id, 2, $this->scheduler->id);
    endedPay($started->id, Money::parse('10', 'SAR'));

    expect(app(EndEnrollment::class)->preview($ziad->enrollmentId, '2026-10-05')['billing']->extraNumber)->toBe($extra->number);
    $done = app(EndEnrollment::class)->handle($ziad->enrollmentId, '2026-10-05', 'Done', $this->scheduler->id);

    expect($done['billing']->outcome)->toBe('none')
        ->and($done['billing']->extraNumber)->toBe($extra->number)
        ->and($extra->refresh()->status)->toBe('void')
        ->and($extra->void_reason)->toBe(__('billing::billing.void_course_ended'))
        ->and(json_decode(DB::table('audit_logs')->where('action', 'enrollment.ended')->where('subject_id', $ziad->enrollmentId)->value('data'), true)['extra_invoice'])->toBe($extra->number);

    // Something was paid on Maryam's: it stays for the office to settle.
    $kept = app(EndEnrollment::class)->handle($maryam->enrollmentId, '2026-10-05', 'Done', $this->scheduler->id);
    expect($kept['billing']->extraNumber)->toBeNull()
        ->and($started->refresh()->status)->toBe('issued');
});
