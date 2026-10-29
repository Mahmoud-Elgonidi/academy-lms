<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\RemoveCoupon;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Domain\CouponTerms;
use Modules\Billing\Domain\Discount;
use Modules\Billing\Domain\Discounts;
use Modules\Billing\Listeners\IssueRenewalInvoice;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditBalanceLow;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\BookExtraLesson;
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
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    $this->kids = User::factory()->withRole(Role::Student)->count(3)->create(['timezone' => 'Africa/Cairo']);
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
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Enrols one of the guardian's children Saturdays and Tuesdays at an hour of the teacher's shift. */
function enrolChild(int $kid, ?string $coupon = null, int $hour = 0): EnrollmentBooked
{
    $t = test();
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse(sprintf('%s %02d:00', $first, 16 + ($hour ?: $kid)), 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $student = $t->kids[$kid]->id;
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $student, $times, 30, $t->scheduler->id);

    return app(EnrollStudent::class)->handle($batch, $student, $t->quran, new EnrollmentTerms($t->plan->id, $t->guardian->id, $t->gulf->id, $coupon), $t->scheduler->id);
}

function makeCoupon(array $data): Coupon
{
    return app(SaveCoupon::class)->handle(null, $data + ['kind' => 'percent'], test()->accountant->id);
}

/** @return array<string, int> what each line of an invoice says, by kind and source */
function linesOf(string $invoiceId): array
{
    return Invoice::query()->findOrFail($invoiceId)->lines()->orderBy('amount_minor', 'desc')->get()
        ->mapWithKeys(fn ($l) => [$l->kind.($l->meta['source'] ?? '') => $l->amount_minor])->all();
}

function payInFull(string $invoiceId): Invoice
{
    $invoice = Invoice::query()->findOrFail($invoiceId);

    return app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', $invoice->outstanding(), 'T-1', CarbonImmutable::now(), null, test()->accountant->id);
}

it('works out discounts: half up, an amount never above the price, the bigger one, or both', function () {
    expect(Discounts::on(40000, 1000, null))->toEqual([new Discount('sibling', 4000, 1000)])
        ->and(Discounts::on(333, 1500, null)[0]->minor)->toBe(50)                       // 49.95 → 50
        ->and(Discounts::on(40000, 0, CouponTerms::amount(50000))[0]->minor)->toBe(40000)
        ->and(Discounts::on(40000, 1000, CouponTerms::percent(2000)))->toEqual([new Discount('coupon', 8000, 2000)])
        ->and(Discounts::on(40000, 2000, CouponTerms::percent(1000)))->toEqual([new Discount('sibling', 8000, 2000)])
        ->and(Discounts::on(40000, 1000, CouponTerms::amount(4000)))->toEqual([new Discount('sibling', 4000, 1000)])   // a tie keeps the coupon
        ->and(Discounts::on(40000, 1000, CouponTerms::percent(5000, withSibling: true)))
        ->toEqual([new Discount('sibling', 4000, 1000), new Discount('coupon', 18000, 5000)])
        ->and(Discounts::on(40000, 0, null))->toBe([]);
});

it('gives the family discount from the second child on, at its rate, as a line of its own', function () {
    app(SaveSiblingDiscount::class)->handle(10, 15);

    $first = enrolChild(0);
    $second = enrolChild(1);
    $third = enrolChild(2);

    expect(linesOf($first->invoice->id))->toBe(['block' => 40000])
        ->and(linesOf($second->invoice->id))->toBe(['block' => 40000, 'discountsibling' => -4000])
        ->and($second->invoice->total->minor)->toBe(36000)
        ->and(linesOf($third->invoice->id))->toBe(['block' => 40000, 'discountsibling' => -6000])
        ->and(Invoice::query()->find($third->invoice->id)->lines()->where('kind', 'discount')->value('description_ar'))->toBe(__('billing::billing.lines.sibling', ['rate' => '15%'], 'ar'))
        ->and(DB::table('audit_logs')->where('action', 'billing.sibling_discount_saved')->exists())->toBeTrue();
});

it('puts a first-invoice coupon on the enrolment; the next block is in full; a payer uses it once', function () {
    makeCoupon(['code' => ' welcome 20 ', 'percent' => 20, 'blocks' => 1]);

    $booked = enrolChild(0, 'Welcome20');
    expect(linesOf($booked->invoice->id))->toBe(['block' => 40000, 'discountcoupon' => -8000])
        ->and(Invoice::query()->find($booked->invoice->id)->lines()->where('kind', 'discount')->value('description_en'))->toBe('Coupon WELCOME20 (20%)');

    payInFull($booked->invoice->id);
    $subscription = Subscription::query()->where('enrollment_id', $booked->enrollmentId)->first();
    [$ar, $en] = $subscription->subjectNames();
    $renewal = app(IssueBlockInvoice::class)->handle($subscription, 2, $ar, $en);
    expect(linesOf($renewal->id))->toBe(['block' => 40000]);

    // The same family, a second child: the coupon is used.
    expect(fn () => enrolChild(1, 'WELCOME20'))->toThrow(ValidationException::class, 'This payer has used coupon WELCOME20 before.');
});

it('refuses a code that cannot be used, saying why', function () {
    $other = Plan::query()->create(['name_ar' => 'ساعة', 'name_en' => 'Hour', 'session_minutes' => 60, 'sessions_per_block' => 4, 'sessions_per_week' => 1, 'validity_days' => 35]);
    makeCoupon(['code' => 'LATER', 'percent' => 10, 'starts_on' => '2026-11-01']);
    makeCoupon(['code' => 'OLD', 'percent' => 10, 'ends_on' => '2026-10-01']);
    makeCoupon(['code' => 'OFF', 'percent' => 10, 'is_active' => false]);
    makeCoupon(['code' => 'HOUR', 'percent' => 10, 'plan_ids' => [$other->id]]);
    makeCoupon(['code' => 'USD5', 'kind' => 'amount', 'amount' => 5, 'currency' => 'USD']);
    makeCoupon(['code' => 'ONCE', 'percent' => 10, 'max_uses' => 1]);
    $quote = fn (string $code, int $kid = 0) => app(BillingApi::class)->quote($this->plan->id, $this->gulf->id, $this->guardian->id, $this->kids[$kid]->id, $code)->couponError;

    expect($quote('NOPE'))->toBe('There is no coupon NOPE.')
        ->and($quote('later'))->toBe('Coupon LATER can be used from 1 November.')
        ->and($quote('OLD'))->toBe('Coupon OLD has ended.')
        ->and($quote('OFF'))->toBe('Coupon OFF is turned off.')
        ->and($quote('HOUR'))->toBe('Coupon HOUR is not for this plan.')
        ->and($quote('USD5'))->toBe('Coupon USD5 is in USD, and this payer pays in another currency.')
        ->and($quote('ONCE'))->toBeNull();

    enrolChild(0, 'ONCE');
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    expect(app(BillingApi::class)->quote($this->plan->id, $this->gulf->id, $stranger->id, $this->kids[1]->id, 'ONCE')->couponError)
        ->toBe('Coupon ONCE has been used as many times as it can.');
});

it('quotes the price with the family discount and a coupon, and says when the coupon waits', function () {
    app(SaveSiblingDiscount::class)->handle(10, 10);
    makeCoupon(['code' => 'FIVE', 'percent' => 5]);
    makeCoupon(['code' => 'HALF', 'percent' => 50, 'with_sibling' => true]);
    enrolChild(0);

    $api = app(BillingApi::class);
    $plain = $api->quote($this->plan->id, $this->gulf->id, $this->guardian->id, $this->kids[1]->id, null);
    $waits = $api->quote($this->plan->id, $this->gulf->id, $this->guardian->id, $this->kids[1]->id, 'five');
    $both = $api->quote($this->plan->id, $this->gulf->id, $this->guardian->id, $this->kids[1]->id, 'HALF');

    expect($plain->total->minor)->toBe(36000)
        ->and($plain->discounts[0]['label'])->toBe('Sibling discount (10%)')
        ->and($waits->total->minor)->toBe(36000)
        ->and($waits->couponError)->toBeNull()
        ->and($waits->couponNote)->toBe('The sibling discount is bigger, so it applies now; the coupon waits for a later invoice.')
        ->and($both->total->minor)->toBe(18000)
        ->and(array_map(fn ($d) => $d['amount']->minor, $both->discounts))->toBe([4000, 18000])
        // The first child pays in full.
        ->and($api->quote($this->plan->id, $this->gulf->id, $this->guardian->id, $this->kids[0]->id, null)->discounts)->toBe([]);
});

it('issues the waiting invoice again with a coupon put on later, not while a proof waits', function () {
    $booked = enrolChild(0);
    makeCoupon(['code' => 'SORRY', 'kind' => 'amount', 'amount' => 100, 'currency' => 'SAR', 'blocks' => 2]);

    $result = app(ApplyCoupon::class)->handle(Subscription::query()->where('enrollment_id', $booked->enrollmentId)->value('id'), 'sorry', $this->accountant->id, reissue: true);

    $old = Invoice::query()->find($booked->invoice->id);
    expect($old->status)->toBe('void')
        ->and($old->void_reason)->toBe('Issued again with coupon SORRY')
        ->and($result['reissued']->number)->toBe('NR-000002')
        ->and(linesOf($result['reissued']->id))->toBe(['block' => 40000, 'discountcoupon' => -10000])
        ->and(json_decode((string) DB::table('audit_logs')->where('action', 'billing.coupon_applied')->value('data'), true))->toMatchArray(['code' => 'SORRY', 'voided' => 'NR-000001', 'invoice' => 'NR-000002']);

    // Taken off again; another coupon, with a proof waiting: refused, nothing changes.
    app(RemoveCoupon::class)->handle($result['redemption']->subscription_id, $this->accountant->id);
    makeCoupon(['code' => 'AGAIN', 'percent' => 10]);
    Storage::fake('local');
    app(SubmitPaymentProof::class)->handle($result['reissued'], $this->guardian->id, 'family', 'bank_transfer', '300', CarbonImmutable::now()->toDateString(), null, null,
        UploadedFile::fake()->image('receipt.jpg'));
    expect(fn () => app(ApplyCoupon::class)->handle($result['redemption']->subscription_id, 'SORRY', $this->accountant->id))
        ->toThrow(ValidationException::class, 'This payer has used coupon SORRY before.')
        ->and(fn () => app(ApplyCoupon::class)->handle($result['redemption']->subscription_id, 'AGAIN', $this->accountant->id, reissue: true))
        ->toThrow(ValidationException::class, 'Invoice NR-000002 has a payment, a proof waiting or an online payment under way');
});

it('settles an invoice that comes to nothing at once, granting the block', function () {
    makeCoupon(['code' => 'SCHOLAR', 'percent' => 100]);

    $booked = enrolChild(0, 'SCHOLAR');
    $invoice = Invoice::query()->find($booked->invoice->id);

    expect($invoice->status)->toBe('paid')
        ->and($invoice->total_minor)->toBe(0)
        ->and($invoice->payments()->count())->toBe(0)
        ->and(Enrollment::query()->find($booked->enrollmentId)->status)->toBe(EnrollmentStatus::Active)
        ->and(app(CreditsApi::class)->balance(Enrollment::query()->find($booked->enrollmentId)->credit_account_id)->remainingMin())->toBe(240)
        ->and(Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', FundingStatus::Funded->value)->count())->toBe(8);
});

it('sells extra lessons at a lesson\'s price less the family discount, adds their minutes, and lets one be booked', function () {
    app(SaveSiblingDiscount::class)->handle(10, 10);
    enrolChild(0);
    $booked = enrolChild(1);
    payInFull($booked->invoice->id);
    $subscription = Subscription::query()->where('enrollment_id', $booked->enrollmentId)->first();
    $account = Enrollment::query()->find($booked->enrollmentId)->credit_account_id;

    $invoice = app(BuyExtraLessons::class)->handle($subscription->id, 2, $this->guardian->id, 'family');

    expect(linesOf($invoice->id))->toBe(['extra' => 10000, 'discountsibling' => -1000])
        ->and($invoice->lines()->where('kind', 'extra')->first()->only(['quantity', 'unit_minor']))->toBe(['quantity' => 2, 'unit_minor' => 5000])
        ->and(fn () => app(BuyExtraLessons::class)->handle($subscription->id, 1, $this->guardian->id))->toThrow(ValidationException::class, 'Invoice '.$invoice->number.' for extra lessons is waiting to be paid.')
        ->and(fn () => app(BuyExtraLessons::class)->handle($subscription->id, 11, $this->guardian->id))->toThrow(ValidationException::class, 'From 1 to 10 lessons.');

    // A renewal still goes out while the extra lessons wait.
    $renewal = app(IssueRenewalInvoice::class);
    $renewal->handle(new CreditBalanceLow($this->academy->id, $account, $this->kids[1]->id, 60));
    expect(Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')->count())->toBe(2);

    // Paid: their minutes fund the lessons waiting first (two of them), and the block stays where it was.
    $funded = fn () => Session::query()->where('enrollment_id', $booked->enrollmentId)->where('funding_status', FundingStatus::Funded->value)->count();
    expect($funded())->toBe(8);
    payInFull($invoice->id);
    expect($funded())->toBe(10)
        ->and($subscription->refresh()->block_no)->toBe(1);

    // With the next block paid too, minutes are free beyond the lessons they can fund (those after it lapses wait): an extra lesson takes 30.
    payInFull(Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')->value('id'));
    $free = app(CreditsApi::class)->balance($account)->availableMin;
    expect($free)->toBeGreaterThanOrEqual(30);
    $extra = app(BookExtraLesson::class)->handle($booked->enrollmentId, CarbonImmutable::parse('2026-10-13 20:00', 'Africa/Cairo'), $this->scheduler->id);
    expect($extra->type->value)->toBe('extra')
        ->and($extra->funding_status)->toBe(FundingStatus::Funded)
        ->and(app(CreditsApi::class)->balance($account)->availableMin)->toBe($free - 30);
});

it('keeps what a used coupon takes off, and writes coupons down in the activity log', function () {
    $saved = makeCoupon(['code' => 'ramadan', 'name' => 'Ramadan', 'percent' => 15, 'blocks' => 3, 'max_uses' => 50]);
    expect($saved->code)->toBe('RAMADAN')
        ->and(fn () => makeCoupon(['code' => 'RAMADAN', 'percent' => 5]))->toThrow(ValidationException::class, 'Another coupon has this code.')
        ->and(fn () => makeCoupon(['code' => 'X', 'percent' => 5]))->toThrow(ValidationException::class, 'A code is 3 to 32 letters or digits');

    enrolChild(0, 'RAMADAN');
    expect($saved->uses())->toBe(1)
        ->and(fn () => app(SaveCoupon::class)->handle($saved->id, ['code' => 'RAMADAN', 'kind' => 'percent', 'percent' => 20, 'blocks' => 3], $this->accountant->id))
        ->toThrow(ValidationException::class, 'This coupon has been used');

    $edited = app(SaveCoupon::class)->handle($saved->id, ['code' => 'RAMADAN', 'kind' => 'percent', 'percent' => 15, 'blocks' => 3, 'is_active' => false, 'name' => 'Ramadan 2027'], $this->accountant->id);
    expect($edited->is_active)->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'billing.coupon_saved')->count())->toBe(2)
        ->and(DB::table('coupons')->where('code', 'RAMADAN')->value('name'))->toBe('Ramadan 2027');
});

it('lets the payer buy extra lessons from the app, once per key, and shows what came off each invoice', function () {
    app(SaveSiblingDiscount::class)->handle(10, 10);
    enrolChild(0);
    $booked = enrolChild(1);
    payInFull($booked->invoice->id);
    $kid = $this->kids[1];
    $token = $this->guardian->createToken('phone')->plainTextToken;
    $this->clearTenant();
    app('auth')->forgetGuards();

    $course = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/balance?learner_id='.$kid->id)
        ->assertOk()->json('data.0');
    expect($course['enrollment_id'])->toBe($booked->enrollmentId)
        ->and($course['extra'])->toMatchArray(['price_minor' => 5000, 'currency' => 'SAR', 'minutes' => 30, 'max' => 10, 'waiting_invoice' => null]);

    $buy = fn (array $body, string $key) => $this->withToken($token)->withHeaders(['Idempotency-Key' => $key, 'Accept-Language' => 'en'])
        ->postJson("/api/v1/family/enrollments/{$booked->enrollmentId}/extra-lessons", $body + ['learner_id' => $kid->id]);
    $first = $buy(['lessons' => 3], 'x-1')->assertCreated()->assertJsonPath('data.total_minor', 13500)->json('data');
    expect($buy(['lessons' => 3], 'x-1')->assertCreated()->json('data'))->toBe($first);
    $buy(['lessons' => 1], 'x-2')->assertUnprocessable()->assertJsonPath('errors.lessons.0', "Invoice {$first['number']} for extra lessons is waiting to be paid.");

    $invoices = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/invoices')->assertOk()->json('data');
    $extra = collect($invoices)->firstWhere('id', $first['id']);
    expect(array_column($extra['lines'], 'kind'))->toBe(['extra', 'discount'])
        ->and($extra['lines'][1]['amount_minor'])->toBe(-1500)
        ->and($extra['lines'][1]['description'])->toBe('Sibling discount (10%)')
        ->and($this->withToken($token)->getJson('/api/v1/family/balance?learner_id='.$kid->id)->json('data.0.extra.waiting_invoice.number'))->toBe($first['number']);

    // The child signed in pays for nothing: no extra lessons for them, and no way to buy them.
    $childToken = (function () use ($kid) {
        $this->actAsTenant($this->academy);
        $t = $kid->createToken('phone')->plainTextToken;
        $this->clearTenant();
        app('auth')->forgetGuards();

        return $t;
    })();
    expect($this->withToken($childToken)->getJson('/api/v1/family/balance')->json('data.0.extra'))->toBeNull();
    $this->withToken($childToken)->withHeaders(['Idempotency-Key' => 'c-1'])
        ->postJson("/api/v1/family/enrollments/{$booked->enrollmentId}/extra-lessons", ['lessons' => 1])->assertNotFound();
});
