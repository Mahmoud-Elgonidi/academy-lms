<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\NextInvoiceNumber;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Actions\SubscribeEnrollment;
use Modules\Billing\Actions\WriteInvoice;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditAccount;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * How an invoice is written (docs/architecture/03, section 5, "قواعد
 * الفلوس"): lines in, VAT once on what is left after discounts, the next
 * gapless number of the academy, an invoice that comes to nothing settled
 * at once; a course put on a plan at its payer's regional price; and the
 * price regions the academy bills in.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    BillingSettings::query()->create(['tax_rate_bp' => 1500, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-', 'due_days' => 3]);
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'sort' => 0]);
    $this->kuwait = PriceRegion::query()->create(['name_ar' => 'الكويت', 'name_en' => 'Kuwait', 'currency' => 'KWD', 'sort' => 1]);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    $this->plan->prices()->create(['price_region_id' => $this->kuwait->id, 'amount_minor' => 12345, 'currency' => 'KWD']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A new course for a new student, its guardian paying, on the plan at this region's price. */
function writingSubscribe(?string $regionId = null, ?string $planId = null, ?string $payerId = null): NewSubscription
{
    $t = test();
    $student = User::factory()->withRole(Role::Student)->create();
    $enrollment = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $t->quran, $t->teacher->id, CarbonImmutable::parse('2026-10-06')));
    $account = app(CreditsApi::class)->openAccount($student->id, 'enrollment', $enrollment);
    app(EnrollmentApi::class)->attachCreditAccount($enrollment, $account);

    return new NewSubscription($enrollment, $student->id, $account, $planId ?? $t->plan->id, $payerId ?? $t->guardian->id, $regionId ?? $t->gulf->id, 'قرآن', 'Quran', $t->admin->id);
}

function writingBlock(int $minor, int $minutes = 240): array
{
    return ['kind' => 'block', 'description_ar' => 'بلوك', 'description_en' => 'Block', 'quantity' => 1, 'unit_minor' => $minor, 'amount_minor' => $minor,
        'meta' => ['block_no' => 2, 'minutes' => $minutes, 'sessions' => 8, 'session_minutes' => 30, 'validity_days' => 42]];
}

function writingDiscount(int $minor): array
{
    return ['kind' => 'discount', 'description_ar' => 'خصم', 'description_en' => 'Discount', 'quantity' => 1, 'unit_minor' => -$minor, 'amount_minor' => -$minor, 'meta' => ['source' => 'sibling']];
}

it('puts a course on its plan at the payer\'s regional price, sets its renewal mark and issues its first block', function () {
    $new = writingSubscribe();
    $invoice = app(SubscribeEnrollment::class)->handle($new);

    $subscription = Subscription::query()->where('enrollment_id', $new->enrollmentId)->sole();
    expect([$subscription->amount_minor, $subscription->currency, $subscription->block_no, $subscription->status])->toBe([40000, 'SAR', 0, 'active'])
        ->and(CreditAccount::query()->findOrFail($new->creditAccountId)->low_balance_min)->toBe(60)        // two lessons of 30
        ->and($invoice->number)->toBe('NR-000001')
        ->and($invoice->subscription_id)->toBe($subscription->id)
        ->and($invoice->lines()->where('kind', 'block')->sole()->meta)->toMatchArray(['block_no' => 1, 'minutes' => 240, 'validity_days' => 42])
        ->and($invoice->total()->minor)->toBe(40000);

    // The same payer's next course in the same currency shares the account; in dinars it gets one of its own.
    app(SubscribeEnrollment::class)->handle(writingSubscribe());
    $kwd = app(SubscribeEnrollment::class)->handle(writingSubscribe($this->kuwait->id));
    expect(BillingAccount::query()->where('payer_id', $this->guardian->id)->pluck('currency')->sort()->values()->all())->toBe(['KWD', 'SAR'])
        ->and([$kwd->currency, $kwd->total_minor])->toBe(['KWD', 12345])
        ->and(DB::table('billing_account_students')->count())->toBe(3);

    // A plan switched off, or with no price in the region, takes no one.
    $this->plan->update(['is_active' => false]);
    expect(fn () => app(SubscribeEnrollment::class)->handle(writingSubscribe()))->toThrow(ValidationException::class, __('billing::billing.errors.no_price'));
});

it('takes over a course paid before the platform without an invoice, and invoices the next block at once when it is at its mark', function () {
    $full = writingSubscribe();
    app(CreditsApi::class)->grant($full->creditAccountId, LotSource::Adjustment, null, 150, null, 'opening-1');
    $subscription = app(SubscribeEnrollment::class)->adopt($full);
    expect($subscription->block_no)->toBe(1)->and(Invoice::query()->count())->toBe(0);

    $low = writingSubscribe();
    app(CreditsApi::class)->grant($low->creditAccountId, LotSource::Adjustment, null, 60, null, 'opening-2');
    app(SubscribeEnrollment::class)->adopt($low);
    expect(Invoice::query()->sole()->lines()->where('kind', 'block')->sole()->meta['block_no'])->toBe(2);
});

it('charges VAT once, on what is left after the discounts, inside the price or on top', function () {
    $new = writingSubscribe();
    app(SubscribeEnrollment::class)->handle($new);
    $subscription = Subscription::query()->where('enrollment_id', $new->enrollmentId)->sole();

    $inside = app(WriteInvoice::class)->handle($subscription, [writingBlock(40000), writingDiscount(4000)], $this->admin->id);
    // 360 SAR including 15%: the VAT is 360 × 15/115 = 46.957, rounded half up once.
    expect([$inside->subtotal_minor, $inside->tax_minor, $inside->total_minor, $inside->paid_minor])->toBe([31304, 4696, 36000, 0])
        ->and([$inside->status, $inside->number, $inside->tax_rate_bp, $inside->prices_include_tax])->toBe(['issued', 'NR-000002', 1500, true])
        ->and($inside->due_at->toDateTimeString())->toBe('2026-10-08 07:00:00')
        ->and($inside->lines()->pluck('amount_minor')->sort()->values()->all())->toBe([-4000, 40000])
        ->and($inside->lines()->pluck('subscription_id')->unique()->all())->toBe([$subscription->id]);

    BillingSettings::query()->update(['prices_include_tax' => false]);
    $onTop = app(WriteInvoice::class)->handle($subscription, [writingBlock(40000), writingDiscount(4000)], $this->admin->id);
    expect([$onTop->subtotal_minor, $onTop->tax_minor, $onTop->total_minor])->toBe([36000, 5400, 41400]);

    // Dinars have three decimals: 12.345 KWD and 5% on top is 0.617 (617.25 fils, half up).
    BillingSettings::query()->update(['tax_rate_bp' => 500]);
    $dinars = writingSubscribe($this->kuwait->id);
    app(SubscribeEnrollment::class)->handle($dinars);
    $kwd = app(WriteInvoice::class)->handle(Subscription::query()->where('enrollment_id', $dinars->enrollmentId)->sole(), [writingBlock(12345)], $this->admin->id);
    expect([$kwd->currency, $kwd->subtotal_minor, $kwd->tax_minor, $kwd->total_minor])->toBe(['KWD', 12345, 617, 12962])
        ->and($kwd->total()->format('en'))->toContain('12.962');

    // Lines that come to less than nothing, or a course of another payer, make no invoice.
    expect(fn () => app(WriteInvoice::class)->handle($subscription, [writingBlock(1000), writingDiscount(2000)], null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(WriteInvoice::class)->forAccount($subscription->billing_account_id, 'KWD', [writingBlock(1000) + ['subscription_id' => $subscription->id]], null))->toThrow(InvalidArgumentException::class);
});

it('settles an invoice that comes to nothing as it is issued: no payment, the minutes granted, the course started', function () {
    Event::fake([InvoicePaid::class]);
    $new = writingSubscribe();
    app(SubscribeEnrollment::class)->handle($new);
    $subscription = Subscription::query()->where('enrollment_id', $new->enrollmentId)->sole();

    $free = app(WriteInvoice::class)->handle($subscription, [writingBlock(40000), writingDiscount(40000)], $this->admin->id);

    expect([$free->status, $free->total_minor, $free->paid_minor, $free->tax_minor])->toBe(['paid', 0, 0, 0])
        ->and($free->paid_at)->not->toBeNull()
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(app(CreditsApi::class)->balance($new->creditAccountId)->remainingMin())->toBe(240)
        ->and($subscription->refresh()->block_no)->toBe(2)
        ->and(app(EnrollmentApi::class)->find($new->enrollmentId)->status->value)->toBe('active');
    Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $e) => $e->invoiceId === $free->id && $e->subscriptionIds === [$subscription->id]);
});

it('numbers invoices without gaps per academy, a number taken in a transaction that fails is taken again, and credit notes count apart', function () {
    $numbers = app(NextInvoiceNumber::class);
    expect(fn () => $numbers->take('NR-'))->toThrow(LogicException::class);

    expect(DB::transaction(fn () => $numbers->take('NR-')))->toBe('NR-000001');
    try {
        DB::transaction(function () use ($numbers) {
            $numbers->take('NR-');
            throw new RuntimeException('the invoice could not be written');
        });
    } catch (RuntimeException) {
    }
    expect(DB::transaction(fn () => $numbers->take('NR-')))->toBe('NR-000002')
        // The prefix is the academy's to change; the count goes on.
        ->and(DB::transaction(fn () => $numbers->take('INV/')))->toBe('INV/000003')
        ->and(DB::transaction(fn () => $numbers->takeCreditNote()))->toBe('CN-000001');

    $this->actAsTenant($this->createAcademy(['slug' => 'other']));
    expect(DB::transaction(fn () => $numbers->take('OT-')))->toBe('OT-000001')
        ->and(DB::transaction(fn () => $numbers->takeCreditNote()))->toBe('CN-000001');
    $this->actAsTenant($this->academy);
    expect(DB::transaction(fn () => $numbers->take('NR-')))->toBe('NR-000004');
});

it('keeps one default region, and never moves a region payers or prices depend on to another currency', function () {
    $save = app(SavePriceRegions::class);
    $rows = fn (array $changes = []) => array_values(array_replace_recursive([
        ['id' => $this->gulf->id, 'name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => false],
        ['id' => $this->kuwait->id, 'name_ar' => 'الكويت', 'name_en' => 'Kuwait', 'currency' => 'KWD', 'is_default' => true],
    ], $changes));

    $save->handle([...$rows(), ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'egp']]);
    expect(PriceRegion::query()->orderBy('sort')->get(['name_en', 'currency', 'is_default'])->toArray())->toBe([
        ['name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => false],
        ['name_en' => 'Kuwait', 'currency' => 'KWD', 'is_default' => true],
        ['name_en' => 'Egypt', 'currency' => 'EGP', 'is_default' => false],
    ]);
    expect(fn () => $save->handle([]))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle($rows([1 => ['currency' => 'ABC']])))->toThrow(ValidationException::class);

    // Egypt has no prices and no payers: it can go, or change currency.
    $egypt = PriceRegion::query()->where('currency', 'EGP')->sole();
    $save->handle([...$rows(), ['id' => $egypt->id, 'name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'USD']]);
    $save->handle($rows());
    expect(PriceRegion::query()->count())->toBe(2);

    // Kuwait's plan is priced in dinars: in Bahraini dinars its prices would be wrong.
    expect(fn () => $save->handle($rows([1 => ['currency' => 'BHD']])))->toThrow(ValidationException::class, __('billing::plans.errors.region_priced', ['currency' => 'KWD']));
    expect($this->kuwait->refresh()->currency)->toBe('KWD');

    // The Gulf bills a payer: it can be renamed, not removed nor moved to another currency.
    app(SubscribeEnrollment::class)->handle(writingSubscribe());
    $save->handle($rows([0 => ['name_en' => 'GCC']]));
    expect($this->gulf->refresh()->name_en)->toBe('GCC')
        ->and(fn () => $save->handle([$rows()[1]]))->toThrow(ValidationException::class, __('billing::plans.errors.region_in_use'))
        ->and(fn () => $save->handle($rows([0 => ['currency' => 'AED']])))->toThrow(ValidationException::class);
});
