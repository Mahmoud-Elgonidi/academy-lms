<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\Payments\ConnectStripeAccount;
use Modules\Billing\Actions\Payments\DisconnectStripeAccount;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\GatewayWebhookItem;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\Gateways\PaymobGateway;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Billing\Payments\StripeConnect;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Two more ways for an academy to be paid (docs/architecture/03, section 5):
 * Paymob in Egypt on the academy's own keys, and the academy's own Stripe
 * account connected to the platform (Connect Standard, direct charges).
 * Either way the money is recorded only when the gateway itself says so.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();                                         // a 400 SAR invoice paid by the guardian
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id');
    config([
        'billing.payments.stripe.platform_secret' => 'sk_test_platform',
        'billing.payments.stripe.connect_client_id' => 'ca_test_platform',
        'billing.payments.stripe.connect_webhook_secret' => 'whsec_connect',
        'billing.payments.stripe.application_fee_bp' => 200,
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function paymobAccount(array $data = []): GatewayAccount
{
    return app(SaveGatewayAccount::class)->handle(null, $data + [
        'gateway' => 'paymob', 'label' => 'Paymob', 'mode' => 'test',
        'secret_key' => 'egy_sk_test_abc', 'public_key' => 'egy_pk_test_xyz', 'api_key' => 'ZXlK.api', 'hmac_secret' => 'HMACSECRET', 'integration_ids' => '4401, 4402',
    ], test()->admin->id);
}

/** An unpaid invoice of 500 EGP for the same payer (Paymob takes pounds). */
function egpInvoice(): Invoice
{
    $t = test();

    return Invoice::query()->create([
        'number' => 'NR-EGP-1', 'billing_account_id' => $t->invoice->billing_account_id, 'status' => 'issued', 'currency' => 'EGP',
        'subtotal_minor' => 50000, 'tax_minor' => 0, 'total_minor' => 50000, 'tax_rate_bp' => 0, 'prices_include_tax' => true,
        'issued_at' => CarbonImmutable::now(), 'due_at' => CarbonImmutable::now()->addDays(3),
    ]);
}

function paymobTxn(array $over = []): array
{
    return array_replace_recursive([
        'id' => 9911, 'pending' => false, 'amount_cents' => 50000, 'success' => true, 'is_auth' => false, 'is_capture' => false,
        'is_standalone_payment' => true, 'is_voided' => false, 'is_refunded' => false, 'is_3d_secure' => true, 'integration_id' => 4401,
        'has_parent_transaction' => false, 'order' => ['id' => 7788], 'created_at' => '2026-10-05T10:05:00.000000', 'currency' => 'EGP',
        'error_occured' => false, 'owner' => 302, 'source_data' => ['type' => 'card', 'pan' => '2346', 'sub_type' => 'MasterCard'],
        'data' => ['message' => 'Approved'],
    ], $over);
}

function paymobCallback(GatewayAccount $account, array $txn, ?string $hmac = null): TestResponse
{
    $hmac ??= hash_hmac('sha512', PaymobGateway::signedString($txn), 'HMACSECRET');

    return test()->call('POST', "http://noor.lms.test/webhooks/payments/{$account->academy_id}/{$account->id}?hmac={$hmac}", [], [], [],
        ['CONTENT_TYPE' => 'application/json'], json_encode(['type' => 'TRANSACTION', 'obj' => $txn]));
}

it('asks for every Paymob key, numbers for the integrations, and keys of the account\'s own mode', function () {
    expect(fn () => paymobAccount(['hmac_secret' => '']))->toThrow(ValidationException::class)
        ->and(fn () => paymobAccount(['integration_ids' => 'card']))->toThrow(ValidationException::class)
        ->and(fn () => paymobAccount(['mode' => 'live']))->toThrow(ValidationException::class);   // egy_sk_test_ on a live account

    $account = paymobAccount();
    expect($account->takes('EGP'))->toBeTrue()->and($account->takes('SAR'))->toBeFalse()
        ->and(app(OnlinePayments::class)->accountsFor($this->invoice))->toBe([]);
});

it('opens Paymob\'s unified checkout through an intention, and records the money when Paymob says so', function () {
    Http::fake([
        'accept.paymob.com/v1/intention/' => Http::response(['id' => 'pi_test_1', 'client_secret' => 'egy_csk_test_1', 'intention_order_id' => 7788, 'status' => 'intended'], 201),
        'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'tok_1']),
        'accept.paymob.com/api/ecommerce/orders/transaction_inquiry' => Http::response(paymobTxn()),
    ]);
    $account = paymobAccount();
    $invoice = egpInvoice();
    $checkout = app(StartCheckout::class)->handle($invoice, $account, $this->payer, 'link');

    expect($checkout->provider_ref)->toBe('7788')
        ->and($checkout->url)->toBe('https://accept.paymob.com/unifiedcheckout/?publicKey=egy_pk_test_xyz&clientSecret=egy_csk_test_1');
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://accept.paymob.com/v1/intention/'
        && $r->hasHeader('Authorization', 'Token egy_sk_test_abc')
        && $r['amount'] === 50000 && $r['currency'] === 'EGP' && $r['payment_methods'] === [4401, 4402]
        && $r['special_reference'] === $checkout->id && str_contains($r['notification_url'], "/webhooks/payments/{$account->academy_id}/{$account->id}"));

    // The callback must carry Paymob's HMAC; then the transaction is asked of Paymob itself.
    paymobCallback($account, paymobTxn(), str_repeat('0', 128))->assertStatus(401);
    paymobCallback($account, paymobTxn())->assertOk();
    paymobCallback($account, paymobTxn())->assertOk();                   // delivered twice: once

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/transaction_inquiry') && $r['order_id'] === 7788 && $r->hasHeader('Authorization', 'Bearer tok_1'));
    $payment = Payment::query()->where('invoice_id', $invoice->id)->sole();
    expect($payment->amount_minor)->toBe(50000)->and($payment->reference)->toBe('9911')
        ->and($invoice->refresh()->status)->toBe('paid')
        ->and(GatewayWebhookItem::query()->count())->toBe(1);
});

it('reads a declined or still pending Paymob transaction, and an order with none yet', function () {
    $account = paymobAccount();
    $gateway = app(GatewayManager::class)->for($account);
    Http::fake([
        'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'tok_1']),
        'accept.paymob.com/api/ecommerce/orders/transaction_inquiry' => Http::sequence()
            ->push(paymobTxn(['success' => false, 'data' => ['message' => 'Insufficient funds']]))
            ->push(paymobTxn(['pending' => true, 'success' => false]))
            ->push(['detail' => 'Not found'], 404),
    ]);

    expect($gateway->fetch('7788'))->status->toBe('failed')->failure->toBe('Insufficient funds')
        ->and($gateway->fetch('7788')->status)->toBe('open')
        ->and($gateway->fetch('7788')->status)->toBe('open');
});

it('connects the academy\'s own Stripe account, and charges on it with the platform\'s fee', function () {
    Http::fake([
        'connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => 'acct_1NOOR', 'livemode' => false, 'scope' => 'read_write']),
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_c1', 'url' => 'https://checkout.stripe.com/c/pay/cs_c1']),
        'api.stripe.com/v1/checkout/sessions/cs_c1' => Http::response(['id' => 'cs_c1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 40000, 'currency' => 'sar', 'payment_intent' => 'pi_c1']),
    ]);
    $connect = app(StripeConnect::class);
    $authorize = $connect->authorizeUrl($this->academy->id, $this->admin->id);
    parse_str((string) parse_url($authorize, PHP_URL_QUERY), $q);
    expect($authorize)->toStartWith('https://connect.stripe.com/oauth/authorize?')
        ->and($q)->toMatchArray(['response_type' => 'code', 'client_id' => 'ca_test_platform', 'scope' => 'read_write'])
        ->and($connect->readState($q['state']))->toBe(['academy' => $this->academy->id, 'user' => $this->admin->id])
        ->and($connect->readState('forged'))->toBeNull();

    // Stripe sends the admin back to the platform's address, and they land on the academy's own settings.
    $this->clearTenant();
    $this->get('http://lms.test/billing/stripe/connected?code=ac_1&state='.urlencode($q['state']))
        ->assertRedirectContains('noor.lms.test')->assertRedirectContains('stripe=connected');
    $this->actAsTenant($this->academy);
    $account = GatewayAccount::query()->sole();
    expect($account->gateway)->toBe('stripe_connect')->and($account->external_id)->toBe('acct_1NOOR')->and($account->mode)->toBe('test')
        ->and(DB::table('audit_logs')->where('action', 'billing.stripe_connected')->exists())->toBeTrue();
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://connect.stripe.com/oauth/token' && $r['code'] === 'ac_1' && $r->hasHeader('Authorization', 'Bearer sk_test_platform'));

    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'family');
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $r->hasHeader('Stripe-Account', 'acct_1NOOR') && $r->hasHeader('Authorization', 'Bearer sk_test_platform')
        && (int) $r['payment_intent_data']['application_fee_amount'] === 800 && $r['metadata']['academy'] === $this->academy->id);

    expect(app(SettleCheckout::class)->handle($checkout)->status)->toBe('paid')
        ->and($this->invoice->refresh()->status)->toBe('paid');
});

it('takes the platform\'s Connect webhook to the right academy and account, signed by the platform\'s secret', function () {
    Http::fake([
        'connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => 'acct_1NOOR', 'livemode' => false]),
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_c2', 'url' => 'https://checkout.stripe.com/c/pay/cs_c2']),
        'api.stripe.com/v1/checkout/sessions/cs_c2' => Http::response(['id' => 'cs_c2', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 40000, 'currency' => 'sar', 'payment_intent' => 'pi_c2']),
    ]);
    $account = app(ConnectStripeAccount::class)->handle('ac_2', $this->admin->id);
    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'link');

    $send = function (array $event, string $secret = 'whsec_connect') {
        $body = json_encode($event);
        $t = time();

        return $this->call('POST', 'http://lms.test/webhooks/stripe-connect', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", $secret),
        ], $body);
    };
    $event = ['id' => 'evt_c1', 'type' => 'checkout.session.completed', 'account' => 'acct_1NOOR',
        'data' => ['object' => ['id' => 'cs_c2', 'metadata' => ['academy' => $this->academy->id, 'checkout_id' => $checkout->id]]]];

    $send($event, 'whsec_other')->assertStatus(401);
    $send(['account' => 'acct_SOMEONE'] + $event)->assertOk();          // another account: nothing happens
    expect($this->invoice->refresh()->status)->toBe('issued');
    $send($event)->assertOk();
    expect($this->invoice->refresh()->status)->toBe('paid');
});

it('disconnects a connected account, and offers no Connect account when the platform keys are gone', function () {
    Http::fake([
        'connect.stripe.com/oauth/token' => Http::response(['stripe_user_id' => 'acct_1NOOR', 'livemode' => false]),
        'connect.stripe.com/oauth/deauthorize' => Http::response(['stripe_user_id' => 'acct_1NOOR']),
    ]);
    $account = app(ConnectStripeAccount::class)->handle('ac_3', $this->admin->id);
    expect(app(OnlinePayments::class)->offers('SAR'))->toBeTrue();

    config(['billing.payments.stripe.connect_client_id' => null]);
    expect(app(OnlinePayments::class)->offers('SAR'))->toBeFalse()->and(app(OnlinePayments::class)->accountsFor($this->invoice))->toBe([]);

    config(['billing.payments.stripe.connect_client_id' => 'ca_test_platform']);
    app(DisconnectStripeAccount::class)->handle($account);
    expect($account->fresh()->is_active)->toBeFalse();
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://connect.stripe.com/oauth/deauthorize' && $r['stripe_user_id'] === 'acct_1NOOR' && $r['client_id'] === 'ca_test_platform');
});

it('shows "connect a Stripe account" on payment settings when the platform can, and the Paymob keys to fill', function () {
    Filament::setCurrentPanel('academy');
    $owner = User::factory()->withRole(Role::Owner)->create();
    $this->actingAs($owner);

    Livewire::test(PaymentSettings::class)->assertActionVisible('connectStripe')
        ->mountAction('add')->fillForm(['gateway' => 'paymob'])
        ->assertFormFieldVisible('integration_ids')->assertFormFieldVisible('hmac_secret')->assertFormFieldHidden('webhook_secret');

    config(['billing.payments.stripe.platform_secret' => null]);
    Livewire::test(PaymentSettings::class)->assertActionHidden('connectStripe');
});
