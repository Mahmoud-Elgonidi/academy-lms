<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Filament\Family\FamilyAccount;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\CourseBillingQuery;
use Modules\Billing\Queries\InvoiceDocument;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Models\CreditAccount;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Cairo.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    $this->kids = User::factory()->withRole(Role::Student)->count(3)->sequence(['name' => 'Ziad'], ['name' => 'Maryam'], ['name' => 'Yousef'])->create(['timezone' => 'Africa/Cairo']);
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
    $this->package = Plan::query()->create(['name_ar' => '12 حصة في أي مادة', 'name_en' => '12 lessons, any subject', 'session_minutes' => 30, 'sessions_per_block' => 12,
        'sessions_per_week' => 3, 'validity_days' => 42, 'credit_scope' => Plan::SHARED]);
    $this->package->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 54000, 'currency' => 'SAR']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Enrols a child with the teacher for eight weeks: on the given weekdays' first dates (Tuesday 6 and
 * Saturday 10 October) at this hour, Cairo time.
 *
 * @param  list<string>  $firsts
 */
function familyEnrol(int $kid, string $subjectId, ?string $planId = null, int $hour = 16, array $firsts = ['2026-10-06', '2026-10-10'], ?string $coupon = null): EnrollmentBooked
{
    $t = test();
    $times = [];
    foreach ($firsts as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse(sprintf('%s %02d:00', $first, $hour), 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $student = $t->kids[$kid]->id;
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $student, $times, 30, $t->scheduler->id);

    return app(EnrollStudent::class)->handle($batch, $student, $subjectId, new EnrollmentTerms($planId ?? $t->plan->id, $t->guardian->id, $t->gulf->id, $coupon), $t->scheduler->id);
}

function familyPay(string $invoiceId): Invoice
{
    $invoice = Invoice::query()->findOrFail($invoiceId);

    return app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', $invoice->outstanding(), 'T-1', CarbonImmutable::now(), null, test()->accountant->id);
}

/** Uses up the reserved minutes of an enrollment's first N funded lessons, as if they were taught. */
function useLessons(string $enrollmentId, int $count): void
{
    Session::query()->where('enrollment_id', $enrollmentId)->where('funding_status', FundingStatus::Funded->value)
        ->whereExists(fn ($q) => $q->from('credit_reservations as r')->whereColumn('r.session_id', 'sessions.id')->where('r.status', 'held'))
        ->orderBy('starts_at')->limit($count)->get()
        ->each(fn (Session $s) => app(CreditsApi::class)->consume($s->id, "session:{$s->id}:consume"));
}

function familySubscription(EnrollmentBooked $booked): Subscription
{
    return Subscription::query()->with('plan')->where('enrollment_id', $booked->enrollmentId)->firstOrFail();
}

it('puts the next blocks of a family renewing in the same week on one invoice', function () {
    app(SaveSiblingDiscount::class)->handle(10, 15);
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);

    // Maryam has four lessons left (her mark is two, and two more come within a week at two a week);
    // Ziad reaches his mark, so his renewal goes out with hers.
    useLessons($maryam->enrollmentId, 4);
    expect(Invoice::query()->count())->toBe(2);
    useLessons($ziad->enrollmentId, 6);

    $family = Invoice::query()->with('lines')->latest('issued_at')->latest('number')->firstOrFail();
    $blocks = $family->lines->where('kind', 'block')->sortBy('description_en')->values();
    expect(Invoice::query()->count())->toBe(3)
        ->and($family->subscription_id)->toBeNull()
        ->and($family->subscriptionIds())->toEqualCanonicalizing([familySubscription($ziad)->id, familySubscription($maryam)->id])
        ->and($blocks->pluck('description_en')->all())->toBe(['Maryam · Quran — 8 sessions (block 2)', 'Ziad · Quran — 8 sessions (block 2)'])
        // Each course keeps its own discount: Maryam is the second child.
        ->and($family->lines->where('kind', 'discount')->sole()->only(['subscription_id', 'amount_minor']))->toBe(['subscription_id' => familySubscription($maryam)->id, 'amount_minor' => -4000])
        ->and($family->total_minor)->toBe(40000 + 36000);

    // Maryam's own mark later finds her block waiting on it, so nothing new goes out.
    expect(app(IssueRenewal::class)->handle(familySubscription($maryam)))->toBeNull();
    useLessons($maryam->enrollmentId, 2);
    expect(Invoice::query()->count())->toBe(3);

    // Paid, it gives each course its block.
    $before = fn (EnrollmentBooked $b) => app(CreditsApi::class)->balance(Enrollment::query()->find($b->enrollmentId)->credit_account_id)->remainingMin();
    [$z, $m] = [$before($ziad), $before($maryam)];
    familyPay($family->id);
    expect(familySubscription($ziad)->block_no)->toBe(2)
        ->and(familySubscription($maryam)->block_no)->toBe(2)
        ->and($before($ziad))->toBe($z + 240)
        ->and($before($maryam))->toBe($m + 240);
});

it('leaves a sibling far from renewing, or every child when the window is off, on their own', function () {
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);

    // Maryam has all eight lessons: nowhere near her mark.
    useLessons($ziad->enrollmentId, 6);
    $renewal = Invoice::query()->latest('number')->firstOrFail();
    expect($renewal->subscription_id)->toBe(familySubscription($ziad)->id)
        ->and($renewal->subscriptionIds())->toBe([familySubscription($ziad)->id]);

    // Within the window but the academy keeps every child's invoice apart.
    BillingSettings::query()->update(['family_invoice_days' => 0]);
    useLessons($maryam->enrollmentId, 6);
    expect(Invoice::query()->latest('number')->firstOrFail()->subscriptionIds())->toBe([familySubscription($maryam)->id]);
});

it('renews only a course that is on: a paused or ended one is neither invoiced on its own nor taken along', function (string $status) {
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);
    // Enrollment's own way to pause or end a course comes later (F2); its status is what Billing reads.
    Enrollment::query()->whereKey($maryam->enrollmentId)->update(['status' => $status]);

    // Maryam's balance reaches her mark: nothing goes out for her.
    useLessons($maryam->enrollmentId, 6);
    expect(Invoice::query()->count())->toBe(2)
        ->and(app(IssueRenewal::class)->handle(familySubscription($maryam)))->toBeNull();

    // Ziad's does: his renewal goes out alone, though Maryam is within the family window.
    useLessons($ziad->enrollmentId, 6);
    expect(Invoice::query()->latest('number')->firstOrFail()->subscriptionIds())->toBe([familySubscription($ziad)->id]);
})->with(['paused', 'ended']);

it('issues a family invoice again with a coupon, keeping every child on it', function () {
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);
    useLessons($maryam->enrollmentId, 4);
    useLessons($ziad->enrollmentId, 6);
    $family = Invoice::query()->latest('number')->firstOrFail();
    app(SaveCoupon::class)->handle(null, ['code' => 'FAMILY10', 'kind' => 'percent', 'percent' => 10], $this->accountant->id);

    $applied = app(ApplyCoupon::class)->handle(familySubscription($maryam)->id, 'FAMILY10', $this->accountant->id, reissue: true);

    $again = $applied['reissued']->load('lines');
    expect($family->refresh()->status)->toBe('void')
        ->and($again->subscriptionIds())->toEqualCanonicalizing([familySubscription($ziad)->id, familySubscription($maryam)->id])
        ->and($again->lines->where('kind', 'block')->pluck('meta.block_no')->unique()->all())->toBe([2])
        ->and($again->lines->where('kind', 'discount')->sole()->subscription_id)->toBe(familySubscription($maryam)->id)
        ->and($again->total_minor)->toBe(40000 + 36000);
});

it('says who a family invoice is for in the lists, the document and the family screens', function () {
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);
    useLessons($maryam->enrollmentId, 4);
    useLessons($ziad->enrollmentId, 6);
    $family = Invoice::query()->latest('number')->firstOrFail();

    app()->setLocale('en');
    $listed = InvoiceResource::withNames(Invoice::query())->whereKey($family->id)->firstOrFail();
    expect($listed->getAttribute('student_name'))->toBe('Maryam, Ziad')
        ->and((int) $listed->getAttribute('course_count'))->toBe(2)
        ->and(InvoiceResource::forLine($listed))->toStartWith('Family invoice (2 courses) · Quran');

    $doc = app(InvoiceDocument::class)->build($family, 'Africa/Cairo');
    expect($doc['family'])->toBeTrue()
        ->and($doc['student']->name)->toBe('Maryam, Ziad')
        ->and($doc['student']->enrollment_id)->toBeNull()
        ->and(array_column($doc['courses'], 'student'))->toBe(['Maryam', 'Ziad'])
        // Each course's block comes before what comes off it.
        ->and($doc['lines']->pluck('kind')->all())->toBe(['block', 'block']);

    $mine = collect(FamilyAccount::invoices($this->guardian->id, 'Africa/Cairo'))->firstWhere('id', $family->id);
    expect($mine['for'])->toBe('Maryam · Quran, Ziad · Quran')
        ->and($mine['family'])->toBeTrue()
        ->and($mine['students'])->toEqualCanonicalizing([$this->kids[0]->id, $this->kids[1]->id]);
});

it('shares one package across a student\'s courses: one invoice, one balance, lessons funded in date order', function () {
    $quran = familyEnrol(0, $this->quran, $this->package->id, hour: 16);
    // Arabic on Saturdays only: a shared package's weekly lessons are across courses.
    $arabic = familyEnrol(0, $this->arabic, $this->package->id, hour: 19, firsts: ['2026-10-10']);

    $account = Enrollment::query()->find($quran->enrollmentId)->credit_account_id;
    expect($quran->invoice)->not->toBeNull()
        ->and(Invoice::query()->find($quran->invoice->id)->lines->where('kind', 'block')->sole()->description_en)->toBe('Shared package — 12 lessons, any subject (block 1)')
        ->and(CreditAccount::query()->find($account)->scope_type)->toBe('student_pool')
        ->and($arabic->invoice)->toBeNull()
        ->and($arabic->joinedPackage)->toBeTrue()
        ->and(Enrollment::query()->find($arabic->enrollmentId)->credit_account_id)->toBe($account)
        ->and(Enrollment::query()->find($arabic->enrollmentId)->status)->toBe(EnrollmentStatus::PendingPayment)
        ->and(Subscription::query()->count())->toBe(1)
        ->and(app(CourseBillingQuery::class)->subscriptionOf($arabic->enrollmentId)->id)->toBe(familySubscription($quran)->id);

    familyPay($quran->invoice->id);

    // Twelve lessons of 30 minutes: the twelve earliest of both courses, whichever subject.
    $all = Session::query()->whereIn('enrollment_id', [$quran->enrollmentId, $arabic->enrollmentId])->orderBy('starts_at')->get();
    expect(Enrollment::query()->find($arabic->enrollmentId)->status)->toBe(EnrollmentStatus::Active)
        ->and($all->where('funding_status', FundingStatus::Funded)->count())->toBe(12)
        ->and($all->take(12)->every(fn (Session $s) => $s->funding_status === FundingStatus::Funded))->toBeTrue()
        ->and($all->take(12)->pluck('enrollment_id')->unique()->count())->toBe(2);

    // The family sees it once, with both subjects.
    app()->setLocale('en');
    $balances = FamilyAccount::balances($this->kids[0]->id, $this->guardian->id);
    expect($balances)->toHaveCount(1)
        ->and($balances[0]['shared'])->toBeTrue()
        ->and($balances[0]['subject'])->toBe('Quran, Arabic')
        ->and($balances[0]['sessions'])->toBe(12);

    // A course joining a package already paid starts at once.
    $tajweed = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
    $third = familyEnrol(0, $tajweed, $this->package->id, hour: 20, firsts: ['2026-10-06']);
    expect(Enrollment::query()->find($third->enrollmentId)->status)->toBe(EnrollmentStatus::Active)
        ->and(Invoice::query()->count())->toBe(1);
});

it('refuses a coupon on a course joining a package, and a plan changing kind once it has students', function () {
    familyEnrol(0, $this->quran, $this->package->id, hour: 16);

    expect(fn () => familyEnrol(0, $this->arabic, $this->package->id, hour: 19, firsts: ['2026-10-10'], coupon: 'ANY'))
        ->toThrow(ValidationException::class, "This course joins the student's shared package and has no invoice of its own.");

    $data = ['name_ar' => 'x', 'name_en' => 'x', 'session_minutes' => 30, 'sessions_per_block' => 12, 'sessions_per_week' => 3];
    expect(fn () => app(SavePlan::class)->handle($this->package, $data + ['credit_scope' => 'enrollment']))
        ->toThrow(ValidationException::class, 'Students are on this plan')
        ->and(app(SavePlan::class)->handle(null, $data + ['credit_scope' => Plan::SHARED])->isShared())->toBeTrue();

    // Another child's courses are their own package, not their brother's.
    $maryam = familyEnrol(1, $this->quran, $this->package->id, hour: 17);
    expect($maryam->invoice)->not->toBeNull()
        ->and(DB::table('credit_accounts')->where('scope_type', 'student_pool')->count())->toBe(2);
});

it('tells the apps which balance is a shared package and which invoice is the family\'s', function () {
    $ziad = familyEnrol(0, $this->quran, hour: 16);
    $maryam = familyEnrol(1, $this->quran, hour: 17);
    familyPay($ziad->invoice->id);
    familyPay($maryam->invoice->id);
    useLessons($maryam->enrollmentId, 4);
    useLessons($ziad->enrollmentId, 6);
    $family = Invoice::query()->latest('number')->firstOrFail();
    familyEnrol(2, $this->quran, $this->package->id, hour: 18);
    familyEnrol(2, $this->arabic, $this->package->id, hour: 19, firsts: ['2026-10-10']);

    $token = $this->guardian->createToken('phone')->plainTextToken;
    $this->clearTenant();
    app('auth')->forgetGuards();

    $balance = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/balance?learner_id='.$this->kids[2]->id)->assertOk()->json('data');
    expect($balance)->toHaveCount(1)
        ->and($balance[0]['shared'])->toBeTrue()
        ->and($balance[0]['subject'])->toBe('Quran, Arabic');

    $invoices = collect($this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/invoices')->assertOk()->json('data'));
    $row = $invoices->firstWhere('id', $family->id);
    expect($row['family'])->toBeTrue()
        ->and($row['student_ids'])->toEqualCanonicalizing([$this->kids[0]->id, $this->kids[1]->id])
        ->and($row['for'])->toBe('Maryam · Quran, Ziad · Quran')
        ->and(collect($row['lines'])->pluck('kind')->all())->toBe(['block', 'block'])
        ->and($invoices->where('family', false)->count())->toBe($invoices->count() - 1);
});
