<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\AutoRenew\FollowUpAutoRenewals;
use Modules\Billing\Actions\Cards\RevokePaymentMethod;
use Modules\Billing\Actions\Cards\SetAutoRenew;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Billing\Payments\Gateways\PaymobGateway;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Privacy\BillingData;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Audit\AuditActor;
use Tests\Support\SavedCardFamily;

uses(SavedCardFamily::class);

/*
 * Saving a card (docs/architecture/02, section 27, J5b): only when the payer
 * ticks the mandate while paying, on the gateway's own page, kept from the
 * gateway's answer; brand, last four and expiry are all anyone reads; one live
 * card per payer and gateway account; removed or let go in one tap.
 */

beforeEach(fn () => $this->savedCardFamily());

afterEach(fn () => CarbonImmutable::setTestNow());

it('keeps the card the gateway kept, only when the payer ticked the mandate, and turns automatic renewal on', function () {
    $this->payOnDemo($this->invoice, save: false);
    expect(PaymentMethod::query()->count())->toBe(0)
        ->and(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeFalse();

    $this->payOnDemo($this->renewal(), save: true);
    $card = PaymentMethod::query()->sole();
    expect($card->only(['payer_id', 'gateway', 'brand', 'last4', 'exp_month', 'exp_year', 'mandate_version']))
        ->toBe(['payer_id' => $this->payer->id, 'gateway' => 'demo', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2034, 'mandate_version' => Mandate::VERSION])
        ->and($card->mandate_hash)->toBe(Mandate::hash('ar'))
        ->and($card->tokens['token'])->toStartWith('demo_tok_')
        // Encrypted at rest, and never in an answer.
        ->and((string) DB::table('payment_methods')->where('id', $card->id)->value('tokens'))->not->toContain('demo_tok_')
        ->and(json_encode($card->toArray()))->not->toContain('demo_tok_')
        ->and(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeTrue();

    $audit = DB::table('audit_logs')->where('action', 'billing.card_saved')->sole();
    expect(json_decode((string) $audit->data, true))->toBe(['brand' => 'visa', 'last4' => '4242', 'mandate_version' => Mandate::VERSION])
        ->and((string) $audit->data)->not->toContain('demo_tok_');
});

it('refuses to save a card without the mandate\'s current words, for someone else, or on a gateway that keeps none', function () {
    expect(fn () => app(StartCheckout::class)->handle($this->invoice, $this->demo, $this->payer->id, 'link', null, true, '2020-01-01'))
        ->toThrow(ValidationException::class, 'The words about saving the card have changed')
        ->and(fn () => app(StartCheckout::class)->handle($this->invoice, $this->demo, $this->student->id, 'link', null, true, Mandate::VERSION))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(StartCheckout::class)->handle($this->invoice, $this->demo, null, 'link', null, true, Mandate::VERSION))
        ->toThrow(ValidationException::class);

    // Paymob keeps no cards in this round: not offered.
    expect((new PaymobGateway(['secret_key' => 'x', 'public_key' => 'x', 'api_key' => 'x', 'hmac_secret' => 'x', 'integration_ids' => '1']))->savesCards())->toBeFalse();
});

it('keeps one live card per payer and gateway account: a newer one replaces the older, whose references go', function () {
    $this->payOnDemo($this->invoice);
    $first = PaymentMethod::query()->sole();
    $this->payOnDemo($this->renewal());

    $first->refresh();
    expect(PaymentMethod::query()->live()->count())->toBe(1)
        ->and($first->revoked_reason)->toBe('replaced')
        ->and($first->tokens)->toBe([])
        ->and(PaymentMethod::query()->live()->sole()->id)->not->toBe($first->id);
});

it('lets the payer read, turn off, turn on and remove in one tap, in the classic look only', function () {
    $this->payOnDemo($this->invoice);
    $card = PaymentMethod::query()->sole();

    $read = $this->cardApi('GET', 'family/cards', $this->payer)->assertOk();
    expect($read->json('data.cards.0'))->toMatchArray(['id' => $card->id, 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2034, 'label' => 'Visa ****4242'])
        ->and($read->json('data.auto_renew'))->toBeTrue()
        ->and($read->json('data.mandate.version'))->toBe(Mandate::VERSION)
        ->and((string) $read->getContent())->not->toContain('demo_tok_')->not->toContain('mandate_hash');

    $off = $this->cardApi('PUT', 'family/cards/auto-renew', $this->payer, ['on' => false])->assertOk();
    expect($off->json('data.auto_renew'))->toBeFalse()->and($off->json('message'))->toContain('أُوقف التجديد التلقائي');
    $this->cardApi('PUT', 'family/cards/auto-renew', $this->payer, ['on' => true])->assertOk()->assertJsonPath('data.auto_renew', true);

    // Someone else's card reads as not found; then the payer removes theirs: automatic renewal goes off with it.
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $this->cardApi('DELETE', "family/cards/{$card->id}", $stranger)->assertNotFound();
    $removed = $this->cardApi('DELETE', "family/cards/{$card->id}", $this->payer)->assertOk();
    expect($removed->json('data.cards'))->toBe([])->and($removed->json('data.auto_renew'))->toBeFalse()
        ->and($card->refresh()->revoked_reason)->toBe('payer')->and($card->tokens)->toBe([])
        ->and(DB::table('audit_logs')->where('action', 'billing.card_revoked')->count())->toBe(1);
    // With no card, it cannot be turned on again.
    $this->cardApi('PUT', 'family/cards/auto-renew', $this->payer, ['on' => true])->assertStatus(422);

    // A learner in the kids' look never reaches cards.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2017-03-01'), null);
    app(GrantConsent::class)->handle($this->student->id, $this->payer->id, 'family');
    app()->forgetInstance(FamilyView::class);
    $this->cardApi('GET', 'family/cards', $this->student)->assertForbidden()->assertJsonPath('type', 'cards-refused');
});

it('saves the card from the app and the portal through the checkout, with the mandate\'s version', function () {
    $this->cardApi('POST', "family/invoices/{$this->invoice->id}/checkout", $this->payer, ['save_card' => true])->assertStatus(422);
    $answer = $this->cardApi('POST', "family/invoices/{$this->invoice->id}/checkout", $this->payer,
        ['account_id' => $this->demo->id, 'save_card' => true, 'mandate_version' => Mandate::VERSION, 'return' => 'portal'])->assertCreated();
    $checkout = PaymentCheckout::query()->findOrFail($answer->json('checkout_id'));
    expect($checkout->save_card)->toBeTrue()->and($checkout->mandate_version)->toBe(Mandate::VERSION);

    // The invoices list says which account keeps cards; the invoice page the words to tick.
    expect($this->cardApi('GET', 'family/invoices', $this->payer)->json('data.0.pay_with.0.saves_cards'))->toBeTrue();
    $shown = $this->cardApi('GET', "family/invoices/{$this->invoice->id}", $this->payer)->assertOk();
    expect($shown->json('save_card'))->toMatchArray(['offered' => true, 'version' => Mandate::VERSION]);

    DemoGateway::decide((string) $checkout->provider_ref, pay: true);
    app(SettleCheckout::class)->handle($checkout);
    expect($this->cardApi('GET', "family/invoices/{$this->invoice->id}", $this->payer)->json('checkout.card_saved'))->toBeTrue();
});

it('turns automatic renewal on only for the payer themselves, never someone signed in as them', function () {
    $this->payOnDemo($this->invoice);
    app(SetAutoRenew::class)->handle($this->payer->id, false);
    AuditActor::use(new AuditActor('platform', 'p-1', 'Support', $this->payer->id, 'imp-1'));
    expect(fn () => app(SetAutoRenew::class)->handle($this->payer->id, true))->toThrow(ValidationException::class);
    // Off is always allowed.
    expect(app(SetAutoRenew::class)->handle($this->payer->id, false))->toBe([]);
    AuditActor::forget();
});

it('lets the cards on a gateway account go when the academy turns it off, and tells each payer once', function () {
    $this->payOnDemo($this->invoice);
    app(SaveGatewayAccount::class)->handle($this->demo, ['label' => 'بطاقة', 'mode' => 'test', 'is_active' => false], $this->admin->id);
    app(SaveGatewayAccount::class)->handle($this->demo->refresh(), ['label' => 'بطاقة', 'mode' => 'test', 'is_active' => false], $this->admin->id);

    expect(PaymentMethod::query()->sole()->revoked_reason)->toBe('gateway_off')
        ->and(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeFalse()
        ->and(Notification::query()->where('template_key', 'billing.card_stopped')->where('user_id', $this->payer->id)->count())->toBe(1)
        ->and(Notification::query()->where('template_key', 'billing.card_stopped')->value('body'))->toContain('Visa ****4242');
});

it('turns automatic renewal off from the signed link in one tap; opening the page changes nothing', function () {
    $this->payOnDemo($this->invoice);
    $link = app(PayLinks::class)->autoRenew($this->payer->id);
    $this->clearTenant();

    $this->get($link)->assertOk()->assertSee('Visa ****4242')->assertSee('أوقفوا التجديد التلقائي');
    $this->actAsTenant($this->academy);
    expect(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeTrue();
    $this->clearTenant();

    $path = URL::temporarySignedRoute('billing.auto-renew.off', now()->addHour(), ['payer' => $this->payer->id], absolute: false);
    $this->post('http://noor.lms.test'.$path)->assertRedirect();
    $this->post('http://noor.lms.test'.str_replace('signature=', 'signature=x', $path))->assertForbidden();
    $this->actAsTenant($this->academy);
    expect(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'billing.auto_renew_off')->value('data'))->toContain('link');
});

it('copies brand, last four and expiry only, and lets every card go on erasure', function () {
    $this->payOnDemo($this->invoice);
    $this->renewal();
    app(FollowUpAutoRenewals::class)->handle();   // the renewal's charge is scheduled
    expect(AutoCharge::query()->sole()->status)->toBe(AutoCharge::SCHEDULED);

    $copy = app(BillingData::class)->export($this->payer->id)->data;
    expect($copy['saved_cards'])->toHaveCount(1)
        ->and($copy['saved_cards'][0])->toMatchArray(['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2034])
        ->and(json_encode($copy))->not->toContain('demo_tok_')->not->toContain('mandate_hash')
        ->and($copy['automatic_charges'][0])->toMatchArray(['status' => 'scheduled']);

    $erased = app(BillingData::class)->erase($this->payer->id);
    expect($erased)->toMatchArray(['cards' => 1])
        ->and(PaymentMethod::query()->sole()->only(['revoked_reason', 'tokens']))->toBe(['revoked_reason' => 'erased', 'tokens' => []])
        ->and(AutoCharge::query()->sole()->status)->toBe(AutoCharge::CANCELLED)
        ->and(BillingAccount::query()->where('payer_id', $this->payer->id)->value('auto_renew'))->toBeFalse();
});

it('reports a card through the revoke action only for its own payer', function () {
    $this->payOnDemo($this->invoice);
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    expect(fn () => app(RevokePaymentMethod::class)->handle(PaymentMethod::query()->sole()->id, $stranger->id))->toThrow(ValidationException::class)
        ->and(PaymentMethod::query()->sole()->revoked_at)->toBeNull();
});

it('shows whoever sees the money the saved cards as brand, last four and expiry, never their references', function () {
    $this->payOnDemo($this->invoice);
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();

    $page = $this->actingAs($accountant)->get('http://noor.lms.test/admin/billing/saved-cards')->assertOk();
    $page->assertSee('Visa ****4242')->assertSee('12/2034')->assertDontSee('demo_tok_');
});

it('tells the payer\'s money screens when a card is kept or removed and automatic renewal is turned off or on', function () {
    config(['database.connections.cards_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('cards_listener')->getPdo();
    $pdo->exec('listen lms_live');
    $raised = function () use ($pdo) {
        $all = [];
        while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
            $all[] = $note['payload'];
        }

        return $all;
    };
    $signal = "payments:{$this->payer->id}";

    $this->payOnDemo($this->invoice);
    $card = PaymentMethod::query()->sole();
    expect($raised())->toContain($signal);

    $this->cardApi('PUT', 'family/cards/auto-renew', $this->payer, ['on' => false])->assertOk();
    expect($raised())->toContain($signal);
    $this->cardApi('PUT', 'family/cards/auto-renew', $this->payer, ['on' => true])->assertOk();
    expect($raised())->toContain($signal);

    $this->cardApi('DELETE', "family/cards/{$card->id}", $this->payer)->assertOk();
    expect($raised())->toContain($signal);
});
