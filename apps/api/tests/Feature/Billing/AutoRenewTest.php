<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\AutoRenew\ChargeAutoRenewal;
use Modules\Billing\Actions\Cards\SetAutoRenew;
use Modules\Billing\Actions\Collections\FollowUpCollections;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Payments\SweepCheckouts;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\SharedKernel\Money\Money;
use Tests\Support\SavedCardFamily;

uses(SavedCardFamily::class);

/*
 * Automatic renewal (docs/architecture/02, section 27, J5b). The fixture:
 * Monday 5 October 2026; the guardian (Riyadh) paid the first block (SAR 400)
 * on the demo gateway, ticking "save my card and renew automatically"; an
 * hour later the course's next block is issued (due Thursday 8 October 08:00
 * UTC, 11:00 in Riyadh). billing:follow-up tells them at once (it is past
 * nine in Riyadh) and charges the card at the due moment, three days on.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->savedCardFamily();
    $this->payOnDemo($this->invoice);
    $this->at('2026-10-05 08:00');
    $this->next = $this->renewal();
});

afterEach(function () {
    DemoGateway::nextCharge(null);
    CarbonImmutable::setTestNow();
});

function j5bFollow(): int
{
    return app(FollowUpCollections::class)->handle();
}

function j5bCharge(): AutoCharge
{
    return AutoCharge::query()->sole();
}

it('tells the payer three days ahead, keeps the reminders quiet, then charges the card once through the gateway\'s word', function () {
    expect(j5bFollow())->toBe(1)->and(j5bFollow())->toBe(0);
    $charge = j5bCharge();
    expect($charge->only(['status', 'amount_minor', 'currency', 'invoice_id']))->toBe(['status' => 'scheduled', 'amount_minor' => 40000, 'currency' => 'SAR', 'invoice_id' => $this->next->id])
        ->and($charge->charge_at->toIso8601ZuluString())->toBe('2026-10-08T08:00:00Z');
    $told = Notification::query()->where('template_key', 'billing.renewal_coming')->sole();
    expect($told->user_id)->toBe($this->payer->id)
        ->and($told->body)->toContain('Visa ****4242')->toContain('400')->toContain('الخميس 8 أكتوبر')->toContain('/pay/auto-renew/')
        ->and($told->action)->toMatchArray(['screen' => 'invoice', 'id' => $this->next->id]);

    // The day before the due day: the timeline is quiet (the card will pay it).
    $this->at('2026-10-07 06:00');
    expect(j5bFollow())->toBe(0);
    $this->at('2026-10-08 07:59');
    expect(j5bFollow())->toBe(0)->and(j5bCharge()->status)->toBe('scheduled');

    $this->at('2026-10-08 08:00');
    j5bFollow();
    $checkout = PaymentCheckout::query()->where('invoice_id', $this->next->id)->sole();
    expect(j5bCharge()->only(['status', 'attempts', 'checkout_id']))->toBe(['status' => 'paid', 'attempts' => 1, 'checkout_id' => $checkout->id])
        ->and($checkout->only(['kind', 'status', 'channel', 'payment_method_id']))->toBe(['kind' => 'saved', 'status' => 'paid', 'channel' => 'auto', 'payment_method_id' => PaymentMethod::query()->sole()->id])
        ->and(Payment::query()->where('invoice_id', $this->next->id)->sole()->checkout_id)->toBe($checkout->id)
        ->and($this->next->refresh()->status)->toBe('paid')
        ->and(Subscription::query()->where('enrollment_id', $this->booked->enrollmentId)->value('block_no'))->toBe(2)
        ->and($this->heard($this->payer->id))->toContain('billing.charge_succeeded');

    // Again, and the job again: nothing more is charged.
    j5bFollow();
    expect(app(ChargeAutoRenewal::class)->handle($charge->id))->toBeNull()
        ->and(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(1)
        ->and(Payment::query()->where('invoice_id', $this->next->id)->count())->toBe(1);
});

it('tries a soft decline once more a day later, saying so, and then pays', function () {
    j5bFollow();
    DemoGateway::nextCharge('insufficient_funds');
    $this->at('2026-10-08 08:00');
    j5bFollow();
    expect(j5bCharge()->only(['status', 'failure', 'attempts']))->toBe(['status' => 'retry', 'failure' => 'insufficient_funds', 'attempts' => 1])
        ->and(j5bCharge()->next_attempt_at->toIso8601ZuluString())->toBe('2026-10-09T08:00:00Z');
    $retry = Notification::query()->where('template_key', 'billing.charge_retry')->sole();
    expect($retry->body)->toContain('الرصيد في البطاقة غير كافٍ')->toContain('الجمعة 9 أكتوبر');

    // While it waits for its retry, no day reminder; then the retry pays.
    $this->at('2026-10-09 07:00');
    expect(j5bFollow())->toBe(0);
    $this->at('2026-10-09 08:00');
    j5bFollow();
    expect(j5bCharge()->only(['status', 'attempts']))->toBe(['status' => 'paid', 'attempts' => 2])
        ->and($this->next->refresh()->status)->toBe('paid');
});

it('never retries a hard decline: the card the bank refuses is let go, the pay link goes, and the timeline goes on', function () {
    j5bFollow();
    DemoGateway::nextCharge('expired_card');
    $this->at('2026-10-08 08:00');
    j5bFollow();
    expect(j5bCharge()->only(['status', 'failure']))->toBe(['status' => 'failed', 'failure' => 'expired_card'])
        ->and(PaymentMethod::query()->sole()->revoked_reason)->toBe('bank')
        ->and(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeFalse();
    $failed = Notification::query()->where('template_key', 'billing.charge_failed')->sole();
    expect($failed->body)->toContain('انتهت صلاحية البطاقة')->toContain('/pay/invoice/');

    $this->at('2026-10-09 08:00');
    j5bFollow();
    $this->at('2026-10-10 08:00');
    j5bFollow();
    expect(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(1)
        ->and($this->heard($this->payer->id))->toContain('billing.overdue');
});

it('never pushes a charge the bank wants the payer to confirm (3-D Secure): the pay link instead, no retry', function () {
    j5bFollow();
    DemoGateway::nextCharge('authentication_required');
    $this->at('2026-10-08 08:00');
    j5bFollow();
    expect(j5bCharge()->only(['status', 'failure']))->toBe(['status' => 'failed', 'failure' => 'authentication_required'])
        ->and(PaymentMethod::query()->sole()->revoked_at)->toBeNull()
        ->and(Notification::query()->where('template_key', 'billing.charge_failed')->sole()->body)->toContain('طلب البنك تأكيدكم');
    $this->at('2026-10-09 09:00');
    j5bFollow();
    expect(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(1);
});

it('asks again about a charge whose answer never came, and never charges it again', function () {
    j5bFollow();
    DemoGateway::nextCharge('network');
    $this->at('2026-10-08 08:00');
    j5bFollow();
    $checkout = PaymentCheckout::query()->where('kind', 'saved')->sole();
    expect($checkout->status)->toBe('open')->and(j5bCharge()->status)->toBe('charging');

    // The next hours ask the gateway, which knows nothing of it; the payer's own page waits meanwhile.
    $this->at('2026-10-08 09:00');
    j5bFollow();
    expect($checkout->refresh()->status)->toBe('open');
    expect(fn () => app(StartCheckout::class)->handle($this->next->refresh(), $this->demo, $this->payer->id, 'link'))
        ->toThrow(ValidationException::class);

    // A day later the sweeper closes it unconfirmed: failed, the pay link, never a second charge.
    $this->at('2026-10-09 09:00');
    app(SweepCheckouts::class)->handle();
    j5bFollow();
    expect($checkout->refresh()->status)->toBe('expired')
        ->and(j5bCharge()->only(['status', 'failure']))->toBe(['status' => 'failed', 'failure' => 'unconfirmed'])
        ->and(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(1)
        ->and(Payment::query()->where('invoice_id', $this->next->id)->count())->toBe(0)
        ->and($this->heard($this->payer->id))->toContain('billing.charge_failed');
});

it('cancels the charge when automatic renewal is turned off before it, and the timeline goes on', function () {
    j5bFollow();
    app(SetAutoRenew::class)->handle($this->payer->id, false);
    expect(j5bCharge()->only(['status', 'cancel_reason']))->toBe(['status' => 'cancelled', 'cancel_reason' => 'turned_off']);

    $this->at('2026-10-08 06:30');
    j5bFollow();
    $this->at('2026-10-08 08:00');
    j5bFollow();
    expect(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(0)
        ->and($this->heard($this->payer->id))->toContain('billing.due_today');
});

it('charges nothing when the invoice was paid in part by hand, has a receipt waiting, was voided, or its course is paused', function (string $case) {
    j5bFollow();
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    match ($case) {
        'paid_by_hand' => app(RecordPayment::class)->handle($this->next->id, 'bank_transfer', Money::of(10000, 'SAR'), 'T-9', CarbonImmutable::now(), null, $accountant->id),
        'proof' => app(SubmitPaymentProof::class)->handle($this->next->refresh(), $this->payer->id, 'family', 'bank_transfer', '400', '2026-10-05', 'T-1', null,
            UploadedFile::fake()->create('receipt.jpg', 120, 'image/jpeg')),
        'voided' => app(VoidInvoice::class)->handle($this->next->id, 'Wrong plan', $accountant->id),
        'course_off' => app(PauseEnrollment::class)->handle($this->booked->enrollmentId, '2026-10-05', '2026-10-30', 'Travel', $this->admin->id),
    };
    $this->at('2026-10-08 08:00');
    j5bFollow();

    expect(j5bCharge()->only(['status', 'cancel_reason']))->toBe(['status' => 'cancelled', 'cancel_reason' => $case])
        ->and(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(0);
})->with(['paid_by_hand', 'proof', 'voided', 'course_off']);

it('does not charge a card that expires first, nor one on an account that does not take the currency: the pay link instead', function (string $case) {
    if ($case === 'card_expires') {
        PaymentMethod::query()->update(['exp_month' => 9, 'exp_year' => 2026]);
    } else {
        app(SaveGatewayAccount::class)->handle($this->demo, ['label' => 'بطاقة', 'mode' => 'test', 'currencies' => ['EGP']], $this->admin->id);
    }
    j5bFollow();

    expect(j5bCharge()->only(['status', 'failure']))->toBe(['status' => 'skipped', 'failure' => $case])
        ->and(Notification::query()->where('template_key', 'billing.renewal_coming')->count())->toBe(0)
        ->and(Notification::query()->where('template_key', 'billing.auto_charge_skipped')->sole()->body)->toContain('/pay/invoice/');
    if ($case === 'card_expires') {
        expect(Notification::query()->where('template_key', 'billing.card_expiring')->count())->toBe(1);
        j5bFollow();
        expect(Notification::query()->where('template_key', 'billing.card_expiring')->count())->toBe(1);   // once
    }
    // The timeline is not held back.
    $this->at('2026-10-07 06:00');
    j5bFollow();
    expect($this->heard($this->payer->id))->toContain('billing.due_tomorrow');
})->with(['card_expires', 'currency']);

it('tells the payer a month before the card they renew with expires, once', function () {
    PaymentMethod::query()->update(['exp_month' => 10, 'exp_year' => 2026]);
    expect(Notification::query()->where('template_key', 'billing.card_expiring')->count())->toBe(0);
    j5bFollow();
    $told = Notification::query()->where('template_key', 'billing.card_expiring')->sole();
    expect($told->body)->toContain('Visa ****4242')->toContain('31 أكتوبر');
    j5bFollow();
    expect(Notification::query()->where('template_key', 'billing.card_expiring')->count())->toBe(1);
});

it('charges renewals only: never extra lessons, nor an invoice that came out before automatic renewal was turned on', function () {
    app(VoidInvoice::class)->handle($this->next->id, 'Again', $this->admin->id);
    $extra = app(BillingApi::class)->sellExtraLessons($this->booked->enrollmentId, 2, $this->admin->id);
    app(SetAutoRenew::class)->handle($this->payer->id, false);
    $again = $this->renewal();
    $this->at('2026-10-05 09:00');
    app(SetAutoRenew::class)->handle($this->payer->id, true);
    j5bFollow();

    expect(AutoCharge::query()->count())->toBe(0)
        ->and(Invoice::query()->whereKey([$extra->id, $again->id])->where('status', 'issued')->count())->toBe(2);
});

it('charges a family invoice for several courses once', function () {
    // A second child of the same guardian, with the second teacher, on the same plan; with the mark at four lessons,
    // the sibling's block comes due within the family window (two weeks) and goes on the same invoice.
    $settings = BillingSettings::current();
    $settings->fill(['family_invoice_days' => 14])->save();
    Plan::query()->update(['renew_at_sessions' => 4]);
    app(VoidInvoice::class)->handle($this->next->id, 'Again', $this->admin->id);
    $sibling = User::factory()->withRole(Role::Student)->create(['timezone' => 'Europe/London']);
    app(PeopleApi::class)->saveStudentProfile($sibling->id, CarbonImmutable::parse('2003-01-01'), null);
    app(PeopleApi::class)->linkGuardian($this->payer->id, $sibling->id);
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse("{$first} 19:00", 'Asia/Riyadh')->addWeeks($i)->getTimestamp();
        }
    }
    $batch = app(PlaceBookingHold::class)->handle($this->other->id, $sibling->id, $times, 30, $this->admin->id);
    $plan = Plan::query()->sole();
    $second = app(EnrollStudent::class)->handle($batch, $sibling->id, $this->quran, new EnrollmentTerms($plan->id, $this->payer->id, PriceRegion::query()->sole()->id), $this->admin->id);
    $this->payOnDemo(Invoice::query()->findOrFail($second->invoice->id));

    $family = app(IssueRenewal::class)->handle(Subscription::query()->with('plan')->where('enrollment_id', $this->booked->enrollmentId)->firstOrFail());
    expect($family->lines->where('kind', 'block')->count())->toBe(2);
    j5bFollow();
    $this->at(j5bCharge()->charge_at->toDateTimeString());
    j5bFollow();

    expect(j5bCharge()->status)->toBe('paid')
        ->and(j5bCharge()->amount_minor)->toBe($family->total_minor)
        ->and(PaymentCheckout::query()->where('kind', 'saved')->count())->toBe(1)
        ->and($family->refresh()->status)->toBe('paid');
});

it('charges a card saved at Stripe off-session, and records it only when Stripe\'s signed webhook and its own word say paid', function () {
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_77', 'status' => 'processing']),
        'api.stripe.com/v1/payment_intents/pi_77' => Http::sequence()
            ->push(['id' => 'pi_77', 'status' => 'processing', 'amount' => 40000, 'currency' => 'sar'])
            ->push(['id' => 'pi_77', 'status' => 'succeeded', 'amount' => 40000, 'currency' => 'sar']),
    ]);
    $stripe = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'stripe', 'label' => 'Stripe', 'mode' => 'test',
        'secret_key' => 'sk_test_s', 'webhook_secret' => 'whsec_s'], $this->admin->id);
    // The payer's card is the one Stripe keeps (as SettleCheckout would have kept it from a Checkout page).
    PaymentMethod::query()->update(['revoked_at' => now(), 'revoked_reason' => 'replaced']);
    PaymentMethod::query()->create(['payer_id' => $this->payer->id, 'gateway_account_id' => $stripe->id, 'gateway' => 'stripe',
        'tokens' => ['customer' => 'cus_S', 'payment_method' => 'pm_1'], 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030,
        'mandate_version' => Mandate::VERSION, 'mandate_hash' => str_repeat('a', 64)]);

    j5bFollow();
    $this->at('2026-10-08 08:00');
    j5bFollow();
    $checkout = PaymentCheckout::query()->where('kind', 'saved')->sole();
    expect($checkout->only(['status', 'provider_ref']))->toBe(['status' => 'open', 'provider_ref' => 'pi_77'])
        ->and(j5bCharge()->status)->toBe('charging');

    $body = json_encode(['id' => 'evt_pi', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_77', 'metadata' => ['checkout_id' => $checkout->id]]]]);
    $t = time();
    $this->call('POST', "http://noor.lms.test/webhooks/payments/{$stripe->academy_id}/{$stripe->id}", [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", 'whsec_s')], $body)->assertOk();
    $this->actAsTenant($this->academy);

    expect($checkout->refresh()->status)->toBe('paid')
        ->and(j5bCharge()->status)->toBe('paid')
        ->and(Payment::query()->where('invoice_id', $this->next->id)->sole()->reference)->toBe('pi_77');
});
