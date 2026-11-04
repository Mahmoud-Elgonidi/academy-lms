<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Payments\Cards\CardFailure;
use Modules\Billing\Payments\Cards\ChargeResult;
use Modules\Billing\Payments\Cards\SavedCharge;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\Gateways\MoyasarGateway;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\Gateways\TapGateway;
use Modules\SharedKernel\Money\Money;

/*
 * Each gateway's saved cards (J5b), against its HTTP API faked: the card kept
 * with a paid page comes from the gateway's own answer; a renewal is a
 * merchant-initiated charge whose every outcome maps to ours: paid, a soft
 * decline (declined, insufficient funds), a hard one (expired card), the bank
 * asking for the payer (3-D Secure: never pushed), and no answer at all
 * (unknown: asked again later, never charged twice).
 */

const SAVED_CHECKOUT = '01a19d00-0000-7000-8000-000000000001';

function savedCharge(array $tokens, string $currency = 'SAR'): SavedCharge
{
    return new SavedCharge(SAVED_CHECKOUT, Money::of(40000, $currency), $tokens, 'Invoice NR-000002 · Noor', 'NR-000002',
        'https://noor.example.com/webhooks/payments/a/b', 'https://noor.example.com/pay/invoice/x');
}

function pageRequest(bool $save): CheckoutRequest
{
    return new CheckoutRequest(SAVED_CHECKOUT, Money::of(40000, 'SAR'), 'Invoice NR-000002', 'NR-000002', 'https://noor.example.com/back',
        'https://noor.example.com/back', 'https://noor.example.com/hook', CarbonImmutable::parse('2026-10-05 08:00', 'UTC'), 'ar',
        'Mona', 'mona@example.com', null, $save);
}

describe('Moyasar', function () {
    $gateway = fn () => new MoyasarGateway(['secret_key' => 'sk_test_m', 'webhook_secret' => 'w']);

    it('asks its page to keep the card, and reads the token, brand, last four and expiry from Moyasar', function () use ($gateway) {
        Http::fake([
            'api.moyasar.com/v1/invoices' => Http::response(['id' => 'inv_1', 'url' => 'https://checkout.moyasar.com/invoices/inv_1']),
            'api.moyasar.com/v1/invoices/inv_1' => Http::response(['id' => 'inv_1', 'status' => 'paid', 'payments' => [['id' => 'pay_1', 'status' => 'paid', 'source' => ['type' => 'creditcard', 'token' => 'token_abc']]]]),
            'api.moyasar.com/v1/tokens/token_abc' => Http::response(['id' => 'token_abc', 'status' => 'active', 'brand' => 'mada', 'last_four' => '1111', 'month' => '09', 'year' => '2029']),
        ]);
        $gateway()->createCheckout(pageRequest(true));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.moyasar.com/v1/invoices' && $r['save_card'] === true);

        $card = $gateway()->savedCard('inv_1');
        expect($card->tokens)->toBe(['token' => 'token_abc'])
            ->and([$card->brand, $card->last4, $card->expMonth, $card->expYear])->toBe(['mada', '1111', 9, 2029])
            ->and($card->complete())->toBeTrue();
    });

    it('charges the token with no 3-D Secure, given our checkout\'s id, and reads each outcome', function (array $answer, int $status, string $expected, ?CardFailure $why) use ($gateway) {
        Http::fake(['api.moyasar.com/v1/payments' => Http::response($answer, $status)]);
        $result = $gateway()->chargeSaved(savedCharge(['token' => 'token_abc']));

        expect($result->status)->toBe($expected)->and($result->failure)->toBe($why);
        Http::assertSent(fn (HttpRequest $r) => $r['given_id'] === SAVED_CHECKOUT && $r['source'] === ['type' => 'token', 'token' => 'token_abc', '3ds' => false, 'manual' => false]
            && $r['amount'] === 40000 && $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_m:')));
    })->with([
        'paid' => [['id' => 'pay_9', 'status' => 'paid', 'source' => []], 200, ChargeResult::PAID, null],
        'declined' => [['id' => 'pay_9', 'status' => 'failed', 'source' => ['response_code' => '05', 'message' => 'Do not honor']], 201, ChargeResult::FAILED, CardFailure::Declined],
        'insufficient funds' => [['id' => 'pay_9', 'status' => 'failed', 'source' => ['response_code' => '51', 'message' => 'INSUFFICIENT_FUNDS']], 201, ChargeResult::FAILED, CardFailure::InsufficientFunds],
        'card expired' => [['id' => 'pay_9', 'status' => 'failed', 'source' => ['response_code' => '54', 'message' => 'Expired Card']], 201, ChargeResult::FAILED, CardFailure::ExpiredCard],
        '3-D Secure' => [['id' => 'pay_9', 'status' => 'initiated', 'source' => ['transaction_url' => 'https://api.moyasar.com/v1/transaction_auths/1']], 201, ChargeResult::ACTION_REQUIRED, CardFailure::AuthenticationRequired],
        'a token no longer valid' => [['type' => 'invalid_request_error', 'message' => 'Token is not active'], 400, ChargeResult::FAILED, CardFailure::InvalidCard],
        'its own error' => [['message' => 'oops'], 502, ChargeResult::UNKNOWN, null],
    ]);

    it('answers unknown when the network fails, and finds the charge by our id afterwards', function () use ($gateway) {
        Http::fake(['api.moyasar.com/v1/payments' => Http::failedConnection()]);
        expect($gateway()->chargeSaved(savedCharge(['token' => 'token_abc']))->status)->toBe(ChargeResult::UNKNOWN);

        Http::fake([
            'api.moyasar.com/v1/payments/'.SAVED_CHECKOUT => Http::response(['id' => SAVED_CHECKOUT, 'status' => 'paid', 'amount' => 40000, 'currency' => 'SAR']),
        ]);
        expect($gateway()->findSaved(SAVED_CHECKOUT))->toBe(SAVED_CHECKOUT)
            ->and($gateway()->fetchSaved(SAVED_CHECKOUT))->toEqual(new CheckoutState(CheckoutState::PAID, Money::of(40000, 'SAR'), SAVED_CHECKOUT));
    });

    it('finds nothing by our id when Moyasar never made the charge', function () use ($gateway) {
        Http::fake(['api.moyasar.com/v1/payments/*' => Http::response(['message' => 'not found'], 404)]);
        expect($gateway()->findSaved(SAVED_CHECKOUT))->toBeNull();
    });
});

describe('Tap', function () {
    $gateway = fn () => new TapGateway(['secret_key' => 'sk_test_t']);

    it('makes the payer a Tap customer and asks to keep the card, then reads card, customer, agreement and expiry', function () use ($gateway) {
        Http::fake([
            'api.tap.company/v2/customers' => Http::response(['id' => 'cus_1']),
            'api.tap.company/v2/charges' => Http::response(['id' => 'chg_1', 'transaction' => ['url' => 'https://tap.company/pay/chg_1']]),
            'api.tap.company/v2/charges/chg_1' => Http::response(['id' => 'chg_1', 'status' => 'CAPTURED', 'card' => ['id' => 'card_9', 'last_four' => '0008', 'brand' => 'VISA'],
                'customer' => ['id' => 'cus_1'], 'payment_agreement' => ['id' => 'payment_agreement_7']]),
            'api.tap.company/v2/card/cus_1/card_9' => Http::response(['id' => 'card_9', 'brand' => 'VISA', 'last_four' => '0008', 'exp_month' => 1, 'exp_year' => 30]),
        ]);
        $gateway()->createCheckout(pageRequest(true));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.tap.company/v2/charges' && $r['save_card'] === true && $r['customer'] === ['id' => 'cus_1']);

        $card = $gateway()->savedCard('chg_1');
        expect($card->tokens)->toBe(['card' => 'card_9', 'customer' => 'cus_1', 'agreement' => 'payment_agreement_7'])
            ->and([$card->brand, $card->last4, $card->expMonth, $card->expYear])->toBe(['VISA', '0008', 1, 2030]);
    });

    it('charges merchant-initiated with a token from the saved card, and reads each outcome', function (array $answer, int $status, string $expected, ?CardFailure $why) use ($gateway) {
        Http::fake([
            'api.tap.company/v2/tokens' => Http::response(['id' => 'tok_5']),
            'api.tap.company/v2/charges' => Http::response($answer, $status),
        ]);
        $result = $gateway()->chargeSaved(savedCharge(['card' => 'card_9', 'customer' => 'cus_1', 'agreement' => 'payment_agreement_7']));

        expect($result->status)->toBe($expected)->and($result->failure)->toBe($why);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.tap.company/v2/tokens' && $r['saved_card'] === ['card_id' => 'card_9', 'customer_id' => 'cus_1']);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.tap.company/v2/charges' && $r['customer_initiated'] === false && $r['threeDSecure'] === false
            && $r['payment_agreement'] === ['id' => 'payment_agreement_7'] && $r['source'] === ['id' => 'tok_5'] && $r['amount'] === 400.0);
    })->with([
        'paid' => [['id' => 'chg_2', 'status' => 'CAPTURED'], 200, ChargeResult::PAID, null],
        'declined' => [['id' => 'chg_2', 'status' => 'DECLINED', 'response' => ['code' => '005', 'message' => 'Do Not Honor']], 200, ChargeResult::FAILED, CardFailure::Declined],
        'insufficient funds' => [['id' => 'chg_2', 'status' => 'DECLINED', 'response' => ['code' => '051', 'message' => 'Insufficient Funds']], 200, ChargeResult::FAILED, CardFailure::InsufficientFunds],
        'card expired' => [['id' => 'chg_2', 'status' => 'DECLINED', 'response' => ['code' => '054', 'message' => 'Expired Card']], 200, ChargeResult::FAILED, CardFailure::ExpiredCard],
        '3-D Secure' => [['id' => 'chg_2', 'status' => 'INITIATED', 'transaction' => ['url' => 'https://tap.company/3ds']], 200, ChargeResult::ACTION_REQUIRED, CardFailure::AuthenticationRequired],
        'its own error' => [['errors' => [['description' => 'busy']]], 503, ChargeResult::UNKNOWN, null],
    ]);

    it('answers unknown when the charge\'s answer never comes', function () use ($gateway) {
        Http::fake(['api.tap.company/v2/tokens' => Http::response(['id' => 'tok_5']), 'api.tap.company/v2/charges' => Http::failedConnection()]);
        expect($gateway()->chargeSaved(savedCharge(['card' => 'card_9', 'customer' => 'cus_1', 'agreement' => 'a']))->status)->toBe(ChargeResult::UNKNOWN);
    });

    it('charges nothing when Tap will not make a token of the saved card', function () use ($gateway) {
        Http::fake(['api.tap.company/v2/tokens' => Http::response(['errors' => [['description' => 'card not found']]], 400)]);
        expect($gateway()->chargeSaved(savedCharge(['card' => 'card_9', 'customer' => 'cus_1', 'agreement' => 'a'])))
            ->toEqual(ChargeResult::failed(CardFailure::InvalidCard));
        Http::assertNotSent(fn (HttpRequest $r) => $r->url() === 'https://api.tap.company/v2/charges');
    });
});

describe('Stripe', function () {
    $gateway = fn () => new StripeGateway(['secret_key' => 'sk_test_s', 'webhook_secret' => 'whsec_s']);

    it('asks Checkout to keep the card for off-session charges, then reads customer, payment method and card', function () use ($gateway) {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']),
            'api.stripe.com/v1/checkout/sessions/cs_1*' => Http::response(['id' => 'cs_1', 'customer' => 'cus_S', 'payment_intent' => ['id' => 'pi_1', 'customer' => 'cus_S',
                'payment_method' => ['id' => 'pm_1', 'card' => ['brand' => 'mastercard', 'last4' => '4444', 'exp_month' => 3, 'exp_year' => 2031]]]]),
        ]);
        $gateway()->createCheckout(pageRequest(true));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $r['payment_intent_data']['setup_future_usage'] === 'off_session' && $r['customer_creation'] === 'always'
            && $r->hasHeader('Idempotency-Key', 'checkout-'.SAVED_CHECKOUT.'-save'));

        $card = $gateway()->savedCard('cs_1');
        expect($card->tokens)->toBe(['customer' => 'cus_S', 'payment_method' => 'pm_1'])
            ->and([$card->brand, $card->last4, $card->expMonth, $card->expYear])->toBe(['mastercard', '4444', 3, 2031]);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'expand[0]=payment_intent.payment_method'));
    });

    it('confirms a PaymentIntent off-session, once by its key, and reads each outcome', function (array $answer, int $status, string $expected, ?CardFailure $why) use ($gateway) {
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response($answer, $status)]);
        $result = $gateway()->chargeSaved(savedCharge(['customer' => 'cus_S', 'payment_method' => 'pm_1']));

        expect($result->status)->toBe($expected)->and($result->failure)->toBe($why);
        Http::assertSent(fn (HttpRequest $r) => $r['off_session'] === 'true' && $r['confirm'] === 'true' && $r['customer'] === 'cus_S'
            && $r['payment_method'] === 'pm_1' && $r['amount'] === 40000 && $r['currency'] === 'sar'
            && $r['metadata']['checkout_id'] === SAVED_CHECKOUT && $r->hasHeader('Idempotency-Key', 'saved-'.SAVED_CHECKOUT));
    })->with([
        'paid' => [['id' => 'pi_9', 'status' => 'succeeded'], 200, ChargeResult::PAID, null],
        'processing' => [['id' => 'pi_9', 'status' => 'processing'], 200, ChargeResult::PENDING, null],
        'declined' => [['error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'generic_decline', 'payment_intent' => ['id' => 'pi_9']]], 402, ChargeResult::FAILED, CardFailure::Declined],
        'insufficient funds' => [['error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => 'insufficient_funds', 'payment_intent' => ['id' => 'pi_9']]], 402, ChargeResult::FAILED, CardFailure::InsufficientFunds],
        'card expired' => [['error' => ['type' => 'card_error', 'code' => 'expired_card', 'payment_intent' => ['id' => 'pi_9']]], 402, ChargeResult::FAILED, CardFailure::ExpiredCard],
        '3-D Secure' => [['error' => ['type' => 'card_error', 'code' => 'authentication_required', 'payment_intent' => ['id' => 'pi_9']]], 402, ChargeResult::ACTION_REQUIRED, CardFailure::AuthenticationRequired],
        'its own error' => [['error' => ['type' => 'api_error']], 500, ChargeResult::UNKNOWN, null],
    ]);

    it('answers unknown when the network fails, finds the charge by our id, and reads a declined one as failed', function () use ($gateway) {
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::failedConnection()]);
        expect($gateway()->chargeSaved(savedCharge(['customer' => 'cus_S', 'payment_method' => 'pm_1']))->status)->toBe(ChargeResult::UNKNOWN);

        Http::fake([
            'api.stripe.com/v1/payment_intents/search*' => Http::response(['data' => [['id' => 'pi_9']]]),
            'api.stripe.com/v1/payment_intents/pi_9' => Http::response(['id' => 'pi_9', 'status' => 'requires_payment_method', 'amount' => 40000, 'currency' => 'sar',
                'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'insufficient_funds']]),
        ]);
        expect($gateway()->findSaved(SAVED_CHECKOUT))->toBe('pi_9')
            ->and($gateway()->fetchSaved('pi_9'))->toEqual(new CheckoutState(CheckoutState::FAILED, Money::of(40000, 'SAR'), failure: 'insufficient_funds'));
    });
});

it('reads the common decline codes into our kinds, soft or hard', function () {
    expect(CardFailure::fromCode('51'))->toBe(CardFailure::InsufficientFunds)
        ->and(CardFailure::fromCode('054'))->toBe(CardFailure::ExpiredCard)
        ->and(CardFailure::fromCode('stolen_card'))->toBe(CardFailure::LostCard)
        ->and(CardFailure::fromCode('authentication_required'))->toBe(CardFailure::AuthenticationRequired)
        ->and(CardFailure::fromCode('05'))->toBe(CardFailure::Declined)
        ->and(CardFailure::fromCode('try_again_later'))->toBe(CardFailure::Processing)
        ->and(CardFailure::InsufficientFunds->retries())->toBeTrue()
        ->and(CardFailure::ExpiredCard->retries())->toBeFalse()->and(CardFailure::ExpiredCard->endsCard())->toBeTrue()
        ->and(CardFailure::AuthenticationRequired->retries())->toBeFalse()->and(CardFailure::AuthenticationRequired->endsCard())->toBeFalse();
});
