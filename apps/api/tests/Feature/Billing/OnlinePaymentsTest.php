<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Payments\SweepCheckouts;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\GatewayWebhookItem;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Billing\Payments\PayLinks;
use Modules\Communication\Models\Notification;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Paying invoices online (docs/architecture/03, section 5): the academy's own
 * gateway accounts, a hosted page per checkout, and the money recorded only
 * when the gateway itself says so, once.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();                     // an unpaid invoice of 400 SAR, paid by the guardian
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function gateway(string $gateway, array $data = []): GatewayAccount
{
    return app(SaveGatewayAccount::class)->handle(null, $data + [
        'gateway' => $gateway, 'label' => ucfirst($gateway), 'mode' => 'test',
        'secret_key' => 'sk_test_'.$gateway, 'webhook_secret' => 'whsec_'.$gateway,
    ], test()->admin->id);
}

function webhook(GatewayAccount $account, string $body, array $headers = []): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', "http://noor.lms.test/webhooks/payments/{$account->academy_id}/{$account->id}", [], [], [], $server, $body);
}

it('pays an invoice on the demo gateway: the money is recorded once, and the enrollment starts', function () {
    $account = gateway('demo');
    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'family');

    expect($checkout->status)->toBe('open')->and($checkout->money())->toEqual(Money::of(40000, 'SAR'))
        ->and($checkout->url)->toContain('/pay/demo/')
        ->and(app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'family')->id)->toBe($checkout->id);   // pressing twice: the same page

    expect(app(SettleCheckout::class)->handle($checkout)->status)->toBe('open');      // nothing paid yet
    DemoGateway::decide($checkout->provider_ref, pay: true);
    $settled = app(SettleCheckout::class)->handle($checkout->refresh());
    app(SettleCheckout::class)->handle($settled);                                      // again: nothing more

    $payment = Payment::query()->where('invoice_id', $this->invoice->id)->sole();
    expect($settled->status)->toBe('paid')
        ->and($payment->method)->toBe('online')->and($payment->checkout_id)->toBe($checkout->id)->and($payment->amount_minor)->toBe(40000)
        ->and($this->invoice->refresh()->status)->toBe('paid')
        ->and(app(EnrollmentApi::class)->find($this->booked->enrollmentId)->status->value)->toBe('active');
});

it('closes a declined checkout, and marks money for a refund when the invoice was settled another way', function () {
    $account = gateway('demo');
    $declined = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'link');
    DemoGateway::decide($declined->provider_ref, pay: false);
    expect(app(SettleCheckout::class)->handle($declined)->status)->toBe('failed');

    $late = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'link');
    app(RecordPayment::class)->handle($this->invoice->id, 'bank_transfer', Money::of(40000, 'SAR'), 'TRX-1', CarbonImmutable::now(), null, $this->admin->id);
    DemoGateway::decide($late->provider_ref, pay: true);

    expect(app(SettleCheckout::class)->handle($late)->status)->toBe('refund_due')
        ->and(Payment::query()->where('invoice_id', $this->invoice->id)->count())->toBe(1);
    expect(fn () => app(StartCheckout::class)->handle($this->invoice->refresh(), $account, $this->payer, 'link'))->toThrow(ValidationException::class);
});

it('opens a Stripe checkout and records it on Stripe\'s signed webhook, once', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 40000, 'currency' => 'sar', 'payment_intent' => 'pi_1']),
    ]);
    $account = gateway('stripe');
    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'family');

    expect($checkout->url)->toBe('https://checkout.stripe.com/c/pay/cs_test_1');
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $r['line_items'][0]['price_data']['unit_amount'] === 40000 && $r['line_items'][0]['price_data']['currency'] === 'sar'
        && $r['client_reference_id'] === $checkout->id && $r->hasHeader('Idempotency-Key', 'checkout-'.$checkout->id)
        && $r->hasHeader('Authorization', 'Bearer sk_test_stripe'));

    $body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1']]]);
    $t = time();
    $signed = ['Stripe-Signature' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$body}", 'whsec_stripe')];

    webhook($account, $body, ['Stripe-Signature' => "t={$t},v1=forged"])->assertStatus(401);
    webhook($account, $body, $signed)->assertOk();
    webhook($account, $body, $signed)->assertOk();                                       // Stripe retries: nothing more

    expect($checkout->refresh()->status)->toBe('paid')->and($checkout->provider_payment_ref)->toBe('pi_1')
        ->and(Payment::query()->where('invoice_id', $this->invoice->id)->count())->toBe(1)
        ->and(GatewayWebhookItem::query()->where('event_id', 'evt_1')->sole()->processed_at)->not->toBeNull();
});

it('records nothing when the gateway charged another amount', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2']),
        'api.stripe.com/v1/checkout/sessions/cs_test_2' => Http::response(['id' => 'cs_test_2', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 100, 'currency' => 'sar']),
    ]);
    $checkout = app(StartCheckout::class)->handle($this->invoice, gateway('stripe'), $this->payer, 'family');

    expect(app(SettleCheckout::class)->handle($checkout))->status->toBe('refund_due')->failure->toBe('amount_mismatch')
        ->and($this->invoice->refresh()->status)->toBe('issued');
});

it('pays with Moyasar: mada and Apple Pay on its invoice page, the webhook checked by its shared secret', function () {
    Http::fake([
        'api.moyasar.com/v1/invoices' => Http::response(['id' => 'inv_m1', 'status' => 'initiated', 'url' => 'https://checkout.moyasar.com/invoices/inv_m1']),
        'api.moyasar.com/v1/invoices/inv_m1' => Http::response(['id' => 'inv_m1', 'status' => 'paid', 'amount' => 40000, 'currency' => 'SAR', 'payments' => [['id' => 'pay_m1', 'status' => 'paid']]]),
    ]);
    $account = gateway('moyasar');
    $checkout = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'link');

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.moyasar.com/v1/invoices' && $r['amount'] === 40000 && $r['currency'] === 'SAR'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_moyasar:')));

    $event = ['id' => 'evt_m1', 'type' => 'payment_paid', 'secret_token' => 'whsec_moyasar', 'data' => ['id' => 'pay_m1', 'status' => 'paid', 'invoice_id' => 'inv_m1']];
    webhook($account, json_encode(['secret_token' => 'guess'] + $event))->assertStatus(401);
    webhook($account, json_encode($event))->assertOk();

    expect($checkout->refresh())->status->toBe('paid')->provider_payment_ref->toBe('pay_m1')
        ->and(GatewayWebhookItem::query()->sole()->payload)->not->toHaveKey('secret_token');
});

it('pays with Tap in Kuwaiti dinars (three decimals), asking Tap about the charge itself', function () {
    DB::table('invoices')->where('id', $this->invoice->id)->update(['currency' => 'KWD', 'total_minor' => 12500, 'subtotal_minor' => 12500]);
    Http::fake([
        'api.tap.company/v2/charges' => Http::response(['id' => 'chg_t1', 'status' => 'INITIATED', 'transaction' => ['url' => 'https://checkout.tap.company/?token=x']]),
        'api.tap.company/v2/charges/chg_t1' => Http::sequence()
            ->push(['id' => 'chg_t1', 'status' => 'INITIATED', 'amount' => 12.5, 'currency' => 'KWD'])
            ->push(['id' => 'chg_t1', 'status' => 'CAPTURED', 'amount' => 12.5, 'currency' => 'KWD', 'reference' => ['payment' => 'tap_p1']]),
    ]);
    $account = gateway('tap');
    $checkout = app(StartCheckout::class)->handle($this->invoice->refresh(), $account, $this->payer, 'link');

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.tap.company/v2/charges' && $r['amount'] === 12.5 && $r['currency'] === 'KWD'
        && $r['source']['id'] === 'src_all' && str_contains($r['post']['url'], "/webhooks/payments/{$account->academy_id}/{$account->id}"));

    // Tap signs its webhooks (hashstring); an unsigned or wrongly signed one is refused.
    $event = ['id' => 'chg_t1', 'status' => 'CAPTURED', 'amount' => 12.5, 'currency' => 'KWD', 'reference' => ['gateway' => 'g1', 'payment' => 'tap_p1'], 'transaction' => ['created' => '1760000000000']];
    $hash = hash_hmac('sha256', 'x_idchg_t1x_amount12.500x_currencyKWDx_gateway_referenceg1x_payment_referencetap_p1x_statusCAPTUREDx_created1760000000000', 'sk_test_tap');
    webhook($account, json_encode($event))->assertStatus(401);
    webhook($account, json_encode($event), ['hashstring' => hash_hmac('sha256', 'something else', 'sk_test_tap')])->assertStatus(401);
    webhook($account, json_encode($event), ['hashstring' => $hash])->assertOk();   // Tap still says INITIATED: nothing yet
    expect($checkout->refresh()->status)->toBe('open');
    webhook($account, json_encode($event + ['retry' => 1]), ['hashstring' => $hash])->assertOk();   // same event: kept once
    app(SettleCheckout::class)->handle($checkout);                                                          // the return or the sweeper asks again

    expect($checkout->refresh())->status->toBe('paid')->provider_payment_ref->toBe('tap_p1')
        ->and(Payment::query()->where('invoice_id', $this->invoice->id)->sole()->amount_minor)->toBe(12500);
});

it('settles or expires checkouts whose webhook never came', function () {
    $account = gateway('demo');
    $paid = app(StartCheckout::class)->handle($this->invoice, $account, $this->payer, 'link');
    DemoGateway::decide($paid->provider_ref, pay: true);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(2));

    expect(app(SweepCheckouts::class)->handle())->toBe(1)->and($paid->refresh()->status)->toBe('paid');

    $other = Invoice::query()->create(['billing_account_id' => $this->invoice->billing_account_id, 'status' => 'issued', 'currency' => 'SAR',
        'total_minor' => 5000, 'subtotal_minor' => 5000, 'number' => 'X-1', 'issued_at' => now(), 'due_at' => now()->addDays(3)]);
    $forgotten = app(StartCheckout::class)->handle($other, $account, $this->payer, 'link');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(70));
    app(SweepCheckouts::class)->handle();
    expect($forgotten->refresh()->status)->toBe('open');                                 // past its time: asked again, half an hour of grace
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(31));
    app(SweepCheckouts::class)->handle();
    expect($forgotten->refresh()->status)->toBe('expired');
});

it('keeps the keys encrypted and never shows them, refuses a test key on a live account, and limits a gateway to its currencies', function () {
    $account = gateway('moyasar');
    expect(DB::table('gateway_accounts')->where('id', $account->id)->value('credentials'))->not->toContain('sk_test_moyasar')
        ->and($account->toArray())->not->toHaveKey('credentials');

    $kept = app(SaveGatewayAccount::class)->handle($account, ['label' => 'mada', 'mode' => 'test', 'secret_key' => '', 'webhook_secret' => ''], $this->admin->id);
    expect($kept->credentials['secret_key'])->toBe('sk_test_moyasar');

    expect(fn () => app(SaveGatewayAccount::class)->handle($account, ['label' => 'mada', 'mode' => 'live', 'secret_key' => 'sk_test_x'], $this->admin->id))
        ->toThrow(ValidationException::class)
        ->and(fn () => gateway('moyasar', ['currencies' => ['KWD']]))->toThrow(ValidationException::class)
        ->and(fn () => gateway('stripe', ['webhook_secret' => '']))->toThrow(ValidationException::class);
});

it('opens the invoice from its signed link, pays, and comes back to say so; nobody else\'s account answers there', function () {
    $account = gateway('demo');
    $this->clearTenant();
    $link = function () {
        $this->actAsTenant($this->academy);
        $url = app(PayLinks::class)->invoice($this->invoice, $this->payer);
        $this->clearTenant();

        return $url;
    };

    $this->get(preg_replace('/signature=[^&]+/', 'signature=forged', $link()))->assertForbidden();
    $page = $this->get($link())->assertOk()->assertSee($this->invoice->number)->assertSee('class="pay-btn', false);
    preg_match('#action="([^"]*/pay/invoice/[^"]+)"#', $page->getContent(), $form);
    $go = $this->post(html_entity_decode($form[1]))->assertRedirect();
    expect($go->headers->get('Location'))->toContain('/pay/demo/');

    $this->get($go->headers->get('Location'))->assertOk();
    preg_match('#action="([^"]*pay=1[^"]*)"#', $this->get($go->headers->get('Location'))->getContent(), $pay);
    $back = $this->post(html_entity_decode($pay[1]))->assertRedirect();
    $this->get($back->headers->get('Location'))->assertRedirect();                       // the way back settles it…

    $this->actAsTenant($this->academy);
    expect($this->invoice->refresh()->status)->toBe('paid');
    $this->clearTenant();

    $stranger = $this->createAcademy(['slug' => 'other']);
    $this->call('POST', "http://noor.lms.test/webhooks/payments/{$stranger->id}/{$account->id}", [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertNotFound();
});

it('puts the payer\'s own pay link in the invoice message', function () {
    expect(Notification::query()->where('user_id', $this->payer)->where('template_key', 'billing.invoice_issued')->sole()->body)
        ->toContain('/pay/invoice/'.$this->invoice->id)->toContain('u='.$this->payer)->toContain('signature=');
});

it('lets the payer pay from their account, choosing among the ways, and nobody else', function () {
    gateway('demo');
    gateway('demo', ['label' => 'Second way']);
    $this->actingAs(User::query()->findOrFail($this->payer));
    Filament\Facades\Filament::setCurrentPanel('family');

    $second = GatewayAccount::query()->where('label', 'Second way')->sole();
    Livewire\Livewire::test(FamilyBilling::class)
        ->assertSee(__('billing::payments.family.pay_now'))
        ->callAction('pay', data: ['account' => $second->id], arguments: ['invoice' => $this->invoice->id])
        ->assertRedirectContains('/pay/demo/');
    expect(PaymentCheckout::query()->sole())->gateway_account_id->toBe($second->id)->channel->toBe('family');

    $this->actingAs(User::factory()->withRole(Role::Guardian)->create());
    Livewire\Livewire::test(FamilyBilling::class)
        ->mountAction('pay', arguments: ['invoice' => $this->invoice->id])->assertStatus(404);
});

it('lets the academy connect a gateway, check its keys, and say how to pay by hand', function () {
    Http::fake(['api.stripe.com/v1/balance' => Http::response(['object' => 'balance'])]);
    $this->actingAs(User::factory()->withRole(Role::Owner)->create());   // owner or accountant: the keys are theirs
    Filament\Facades\Filament::setCurrentPanel('academy');

    $page = Livewire\Livewire::test(PaymentSettings::class)
        ->callAction('add', data: ['gateway' => 'stripe', 'label' => 'International card', 'mode' => 'test', 'currencies' => ['USD', 'GBP'],
            'secret_key' => 'sk_test_abc', 'webhook_secret' => 'whsec_abc', 'is_active' => true])
        ->assertHasNoActionErrors();
    $account = GatewayAccount::query()->sole();
    expect($account)->gateway->toBe('stripe')->currencies->toBe(['USD', 'GBP'])
        ->and($account->takes('SAR'))->toBeFalse();

    $page->assertSee("/webhooks/payments/{$account->academy_id}/{$account->id}")
        ->callAction('test', arguments: ['account' => $account->id])
        ->assertNotified(__('billing::payments.settings.test_ok'))
        ->set('manualNote', 'IBAN SA00 0000')->call('saveManualNote');
    expect(BillingSettings::current()->manual_payment_note)->toBe('IBAN SA00 0000');

    $this->actingAs($this->admin);
    $this->clearTenant();
    $this->get('http://noor.lms.test/admin/settings/payments')->assertForbidden();
});
