<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SubscribeEnrollment;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Two people at the same money at the same moment, against the real
 * database: a second connection plays the other side (a second accountant,
 * the gateway's webhook racing the payer's return, a second tap in the app).
 * Either it waits for this side's row lock until this side is done (it is
 * given 300 ms and gives up, which shows it was held back), or it commits
 * just before this side looks; this side then sees it. Never two payments,
 * two numbers alike, or a coupon's last use taken twice. The fixture: an
 * unpaid invoice of 400 SAR for 8 lessons, paid by the guardian.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = (string) DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id');
    $this->subscription = Subscription::query()->where('enrollment_id', $this->booked->enrollmentId)->sole();
    config(['database.connections.pgsql_money_rival' => config('database.connections.'.config('database.default'))]);
    $this->rival = DB::connection('pgsql_money_rival');
    $this->rival->select("select set_config('app.tenant_id', ?, false)", [$this->academy->id]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Right after this side sends the statement $match picks (and before its next one), the other side does $then. */
function moneyAfter(Closure $match, Closure $then): void
{
    $state = 'waiting';
    DB::connection()->beforeExecuting(function (string $query) use ($match, $then, &$state) {
        if ($state === 'sent') {
            $state = 'done';
            $then();
        } elseif ($state === 'waiting' && $match($query)) {
            $state = 'sent';
        }
    });
}

/** Just before this side first sends the statement $match picks, the other side does $then (committed). */
function moneyBefore(Closure $match, Closure $then): void
{
    $fired = false;
    DB::connection()->beforeExecuting(function (string $query) use ($match, $then, &$fired) {
        if (! $fired && $match($query)) {
            $fired = true;
            $then();
        }
    });
}

function lockingRead(string $table): Closure
{
    return fn (string $query) => str_starts_with($query, 'select') && str_contains($query, "from \"{$table}\"") && str_ends_with($query, 'for update');
}

/** The other side does $work in a transaction of its own; true when it had to wait for a lock this side held, and gave up. */
function moneyRivalWaits(Closure $work): bool
{
    $rival = test()->rival;
    $rival->statement("set lock_timeout = '300ms'");
    try {
        $rival->transaction(fn () => $work($rival));

        return false;
    } catch (QueryException $e) {
        if ($e->getCode() === '55P03') {        // lock_not_available
            return true;
        }
        throw $e;
    } finally {
        $rival->statement('set lock_timeout = 0');
    }
}

function moneyNumbers(): array
{
    return Invoice::query()->pluck('number')->map(fn (string $n) => (int) preg_replace('/\D/', '', $n))->sort()->values()->all();
}

it('approves a proof once when two accountants approve it at the same moment', function () {
    $proof = app(SubmitPaymentProof::class)->handle($this->invoice, $this->payer, 'family', 'instapay', '400', '2026-10-05', 'IP-1', null, UploadedFile::fake()->create('r.jpg', 50, 'image/jpeg'));
    $waited = null;
    // The other accountant reaches for the same proof while this one is approving it.
    moneyAfter(lockingRead('manual_payment_proofs'), function () use (&$waited, $proof) {
        $waited = moneyRivalWaits(fn (Connection $db) => $db->select('select status from manual_payment_proofs where id = ? for update', [$proof->id]));
    });

    app(ApprovePaymentProof::class)->handle($proof->id, Money::of(40000, 'SAR'), 'instapay', null, CarbonImmutable::now(), null, $this->admin->id);

    // Once this one is done, the other finds it decided.
    expect($waited)->toBeTrue()
        ->and(fn () => app(ApprovePaymentProof::class)->handle($proof->id, Money::of(40000, 'SAR'), 'instapay', null, CarbonImmutable::now(), null, $this->admin->id))
        ->toThrow(ValidationException::class, __('billing::proofs.errors.decided'));
    expect(Payment::query()->count())->toBe(1)
        ->and($this->invoice->refresh()->paid_minor)->toBe(40000)
        ->and(app(CreditsApi::class)->balance($this->subscription->credit_account_id)->remainingMin())->toBe(240);
});

it('records an online payment once when the webhook and the payer\'s return settle it together', function () {
    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Demo', 'mode' => 'test'], $this->admin->id);
    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'family');
    DemoGateway::decide($checkout->provider_ref, pay: true);
    $waited = null;
    moneyAfter(lockingRead('payment_checkouts'), function () use (&$waited, $checkout) {
        $waited = moneyRivalWaits(fn (Connection $db) => $db->select('select status from payment_checkouts where id = ? for update', [$checkout->id]));
    });

    app(SettleCheckout::class)->handle($checkout);
    app(SettleCheckout::class)->handle($checkout->refresh());

    expect($waited)->toBeTrue()
        ->and(Payment::query()->where('checkout_id', $checkout->id)->count())->toBe(1)
        ->and($this->invoice->refresh()->status)->toBe('paid');
    // And the database itself takes one payment per checkout.
    expect(fn () => DB::table('payments')->insert(['id' => (string) Str::uuid(), 'invoice_id' => $this->invoice->id, 'method' => 'online', 'amount_minor' => 1,
        'currency' => 'SAR', 'received_at' => now(), 'created_at' => now(), 'checkout_id' => $checkout->id]))->toThrow(UniqueConstraintViolationException::class);
});

it('numbers invoices issued at the same moment one after the other, with no number twice and none skipped', function () {
    app(RecordPayment::class)->handle($this->invoice->id, 'cash', Money::of(40000, 'SAR'), null, CarbonImmutable::now(), null, $this->admin->id);
    $prefix = substr((string) $this->invoice->number, 0, -6);

    // Another invoice takes the next number just before this one asks for it: this one gets the one after.
    moneyBefore(fn (string $q) => str_starts_with($q, 'insert into invoice_sequences'), fn () => $this->rival->transaction(function (Connection $db) use ($prefix) {
        $db->statement('insert into invoice_sequences (academy_id, next_no) values (app_current_tenant(), 1) on conflict (academy_id) do nothing');
        $no = (int) $db->selectOne('select next_no from invoice_sequences for update')->next_no;
        $db->update('update invoice_sequences set next_no = next_no + 1');
        $db->insert("insert into invoices (id, number, billing_account_id, status, currency, created_at, updated_at) values (?, ?, ?, 'issued', 'SAR', now(), now())",
            [(string) Str::uuid(), $prefix.str_pad((string) $no, 6, '0', STR_PAD_LEFT), $this->invoice->billing_account_id]);
    }));
    // And while this one holds the counter, a third has to wait for it.
    $waited = null;
    moneyAfter(lockingRead('invoice_sequences'), function () use (&$waited) {
        $waited = moneyRivalWaits(fn (Connection $db) => $db->update('update invoice_sequences set next_no = next_no + 1'));
    });

    $extra = app(BuyExtraLessons::class)->handle($this->subscription->id, 2, $this->admin->id);

    expect($waited)->toBeTrue()
        ->and($extra->number)->toBe($prefix.'000003')
        ->and(moneyNumbers())->toBe([1, 2, 3])
        ->and((int) DB::table('invoice_sequences')->value('next_no'))->toBe(4);
});

it('makes one invoice for extra lessons when the payer taps twice at the same moment', function () {
    app(RecordPayment::class)->handle($this->invoice->id, 'cash', Money::of(40000, 'SAR'), null, CarbonImmutable::now(), null, $this->admin->id);
    $waited = null;
    moneyAfter(lockingRead('subscriptions'), function () use (&$waited) {
        $waited = moneyRivalWaits(fn (Connection $db) => $db->select('select id from subscriptions where id = ? for update', [$this->subscription->id]));
    });

    $first = app(BuyExtraLessons::class)->handle($this->subscription->id, 2, $this->student->id, 'family');

    expect($waited)->toBeTrue()
        ->and(fn () => app(BuyExtraLessons::class)->handle($this->subscription->id, 2, $this->student->id, 'family'))
        ->toThrow(ValidationException::class, __('billing::discounts.errors.extra_waiting', ['number' => $first->number]));
    expect(Invoice::query()->forSubscription($this->subscription->id, 'extra')->count())->toBe(1);
});

it('gives a coupon\'s last use to one family when two put it on at the same moment', function () {
    $coupon = app(SaveCoupon::class)->handle(null, ['code' => 'LAST1', 'kind' => 'percent', 'percent' => 10, 'max_uses' => 1], $this->admin->id);
    // Another family's course, whose payer puts the same code on at the same moment.
    $guardian = User::factory()->withRole(Role::Guardian)->create();
    $student = User::factory()->withRole(Role::Student)->create();
    $enrollment = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $this->quran, $this->teacher->id, CarbonImmutable::parse('2026-10-06')));
    $credit = app(CreditsApi::class)->openAccount($student->id, 'enrollment', $enrollment);
    app(EnrollmentApi::class)->attachCreditAccount($enrollment, $credit);
    app(SubscribeEnrollment::class)->handle(new NewSubscription($enrollment, $student->id, $credit, Plan::query()->value('id'), $guardian->id, PriceRegion::query()->value('id'), 'قرآن', 'Quran'));
    $theirs = Subscription::query()->where('enrollment_id', $enrollment)->sole();

    // Just after this side counted the coupon's uses, the other family's ApplyCoupon takes the coupon and its use.
    $waited = null;
    moneyAfter(fn (string $q) => str_starts_with($q, 'select count(*) as "aggregate" from "coupon_redemptions"'), function () use (&$waited, $coupon, $theirs) {
        $waited = moneyRivalWaits(function (Connection $db) use ($coupon, $theirs) {
            $db->select('select id from coupons where id = ? for update', [$coupon->id]);
            $db->insert('insert into coupon_redemptions (id, coupon_id, billing_account_id, subscription_id, applied_at) values (?, ?, ?, ?, now())',
                [(string) Str::uuid(), $coupon->id, $theirs->billing_account_id, $theirs->id]);
        });
    });

    app(ApplyCoupon::class)->handle($this->subscription->id, 'LAST1', $this->admin->id);

    expect($waited)->toBeTrue()
        ->and(Coupon::query()->findOrFail($coupon->id)->uses())->toBe(1)
        ->and(fn () => app(ApplyCoupon::class)->handle($theirs->id, 'LAST1', $guardian->id))->toThrow(ValidationException::class, __('billing::discounts.errors.used_up', ['code' => 'LAST1']));
});
