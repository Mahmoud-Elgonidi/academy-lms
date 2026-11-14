<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\Plans\SaveTopUpPack;
use Modules\Platform\Actions\Recharging\ChargeAutoTopUp;
use Modules\Platform\Actions\Recharging\RemovePlatformCard;
use Modules\Platform\Actions\Recharging\SaveAutoRecharge;
use Modules\Platform\Actions\Recharging\SettleTopUpPayment;
use Modules\Platform\Actions\Recharging\StartTopUpCheckout;
use Modules\Platform\Actions\TopUps\ActivateTopUp;
use Modules\Platform\Actions\TopUps\RequestTopUp;
use Modules\Platform\Console\RechargeReport;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Domain\Recharging\AutoRechargeRules;
use Modules\Platform\Filament\Console\Pages\RechargeRevenuePage;
use Modules\Platform\Filament\Pages\UsagePage;
use Modules\Platform\Metering\MeteringSweep;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\PlatformWebhookItem;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Payments\CardDecline;
use Modules\Platform\Plans\PlanWarning;
use Modules\Platform\Recharging\Receipts;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Events\AutoRechargeTurnedOff;
use Modules\SharedKernel\Plans\Events\RechargeFailed;
use Modules\SharedKernel\Plans\Events\RechargeSucceeded;
use Modules\SharedKernel\Plans\Meter;

/*
 * Recharging (docs/architecture/02, section 11, "الشحن"): the platform is the
 * merchant. An academy buys a pack priced in its currency on Stripe's page
 * (the platform's own account, its own small client, faked here); only Stripe
 * saying "paid" adds the lot, once, with a numbered receipt; a signed webhook
 * only triggers that. A saved card buys automatically below a threshold, at
 * most so many times a month; a soft decline is tried once more a day later,
 * then automatic recharge turns off and the staff hear of it. Transfers stay,
 * now with a receipt of what arrived. The console reads the revenue in each
 * currency on its own.
 *
 * Academy "noor" (SAR, Africa/Cairo) on a plan of 100 video minutes and 50 AI
 * credits a month; Tuesday 10 November 2026, 09:00 UTC.
 */

const STRIPE_BASE = 'https://api.stripe.test';

beforeEach(function () {
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 09:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['email' => 'owner@noor.test', 'locale' => 'ar']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $plan = SaasPlan::query()->create(['code' => 'r-'.Str::lower(Str::random(6)), 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => ['ai'], 'video_minutes' => 100, 'ai_uses' => 50]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    app(PlanWarning::class)->forget($this->academy->id);
    $this->platformOwner = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->platformOwner->forceFill(['is_owner' => true])->save();
    $this->pack = TopUpPack::query()->create(['limit' => 'video_minutes', 'amount' => 600, 'name_ar' => 'متوسطة', 'name_en' => 'Medium', 'prices' => ['SAR' => 5000, 'USD' => 1400], 'sort' => 1]);
    config([
        'billing.payments.stripe.platform_secret' => 'sk_test_platform',
        'billing.payments.stripe.base' => STRIPE_BASE,
        'platform.payments.stripe.webhook_secret' => 'whsec_platform',
    ]);
    $this->stripe = (object) ['session' => 'open', 'intent' => 'succeeded', 'charge' => 'succeeded', 'decline' => null, 'paid' => 5000, 'calls' => []];
    fakePlatformStripe();
    $this->actingAs($this->owner);
    app()->setLocale('ar');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** Stripe's API for the platform's account, answering from $this->stripe. */
function fakePlatformStripe(): void
{
    Http::fake(function (HttpRequest $request) {
        $s = test()->stripe;
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $s->calls[] = $request->method().' '.$path;
        $card = ['id' => 'pm_card_1', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]];

        return match (true) {
            $path === '/v1/customers' => Http::response(['id' => 'cus_noor']),
            $path === '/v1/checkout/sessions' && $request->method() === 'POST' => Http::response(['id' => 'cs_test_'.count($s->calls), 'url' => 'https://checkout.stripe.test/pay/'.count($s->calls)]),
            str_starts_with($path, '/v1/checkout/sessions/') => Http::response([
                'id' => substr($path, strlen('/v1/checkout/sessions/')),
                'status' => $s->session === 'paid' ? 'complete' : $s->session,
                'payment_status' => $s->session === 'paid' ? 'paid' : 'unpaid',
                'amount_total' => $s->paid, 'currency' => 'sar', 'customer' => 'cus_noor',
                'payment_intent' => ['id' => 'pi_checkout_1', 'customer' => 'cus_noor', 'payment_method' => $card],
            ]),
            $path === '/v1/payment_intents' && $request->method() === 'POST' => $s->charge === 'declined'
                ? Http::response(['error' => ['type' => 'card_error', 'code' => 'card_declined', 'decline_code' => $s->decline ?? 'generic_decline', 'payment_intent' => ['id' => 'pi_declined_'.count($s->calls)]]], 402)
                : Http::response(['id' => 'pi_auto_'.count($s->calls), 'status' => $s->charge]),
            str_starts_with($path, '/v1/payment_intents/') => Http::response(['id' => substr($path, strlen('/v1/payment_intents/')), 'status' => $s->intent, 'amount' => $s->paid, 'currency' => 'sar']),
            str_ends_with($path, '/detach') => Http::response(['id' => 'pm_card_1']),
            default => Http::response(['error' => ['code' => 'unknown']], 404),
        };
    });
}

/** A Stripe event delivered to the platform's webhook, signed with $secret. */
function deliverStripeEvent(array $event, string $secret = 'whsec_platform')
{
    $payload = (string) json_encode($event);
    $t = time();
    $signature = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret);

    return test()->call('POST', 'http://lms.test/webhooks/platform/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $payload);
}

function sessionEvent(string $id, TopUpPurchase $purchase, string $type = 'checkout.session.completed'): array
{
    return ['id' => $id, 'type' => $type, 'data' => ['object' => ['id' => $purchase->provider_ref, 'metadata' => ['purchase' => $purchase->id, 'academy' => $purchase->academy_id]]]];
}

function purchaseLots(string $academyId): int
{
    return DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $academyId)->where('kind', Ledger::PURCHASE)->where('ref_type', 'purchase')->count();
}

/** A saved card and automatic recharge of video minutes below $threshold, at most $cap times a month. */
function autoRechargeOn(int $threshold = 50, int $cap = 2): void
{
    PlatformPaymentMethod::query()->create(['academy_id' => test()->academy->id, 'customer_ref' => 'cus_noor', 'method_ref' => 'pm_card_1', 'brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]);
    app(SaveAutoRecharge::class)->handle(Meter::VideoMinutes, ['enabled' => true, 'pack_id' => test()->pack->id, 'threshold' => $threshold, 'monthly_cap' => $cap], test()->owner->id);
}

it('prices a pack in several currencies from the console, audited by currency only', function () {
    $pack = app(SaveTopUpPack::class)->handle(null, ['limit' => 'ai_uses', 'amount' => 720, 'name_ar' => 'متوسطة', 'name_en' => 'Medium', 'prices' => ['SAR' => 9900, 'EGP' => 120000]]);
    expect($pack->fresh()->prices)->toBe(['EGP' => 120000, 'SAR' => 9900])
        ->and($pack->priceIn('EGP')?->format('en'))->toContain('1,200')
        ->and($pack->priceIn('USD'))->toBeNull();
    $audit = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.topup_pack_saved')->sole();
    expect(json_decode($audit->data, true)['currencies'])->toBe(['SAR', 'EGP'])
        ->and($audit->data)->not->toContain('9900');

    // Ships with no prices: a pack with none is bought by transfer alone; the database keeps it an object.
    $bare = app(SaveTopUpPack::class)->handle(null, ['limit' => 'video_minutes', 'amount' => 300, 'name_ar' => 'صغيرة', 'name_en' => 'Small']);
    expect($bare->fresh()->prices)->toBe([])
        ->and(DB::connection('pgsql_platform')->selectOne('select jsonb_typeof(prices) as t from saas_topup_packs where id = ?', [$bare->id])->t)->toBe('object');

    expect(fn () => app(SaveTopUpPack::class)->handle(null, ['limit' => 'ai_uses', 'amount' => 5, 'name_ar' => 'x', 'name_en' => 'x', 'prices' => ['XYZ' => 100]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveTopUpPack::class)->handle(null, ['limit' => 'ai_uses', 'amount' => 5, 'name_ar' => 'x', 'name_en' => 'x', 'prices' => ['KWD' => 1005]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveTopUpPack::class)->handle(null, ['limit' => 'ai_uses', 'amount' => 5, 'name_ar' => 'x', 'name_en' => 'x', 'prices' => ['SAR' => 0]]))->toThrow(ValidationException::class);
});

it('opens Stripe\'s page for a pack priced in the academy\'s currency, never while signed in as someone', function () {
    Filament::setCurrentPanel('academy');
    Livewire::test(UsagePage::class)
        ->assertSee(__('platform::recharge.buy.label'))
        ->callAction('buyOnline', ['pack' => $this->pack->id, 'save_card' => true], ['meter' => 'video_minutes'])
        ->assertHasNoActionErrors()
        ->assertRedirect('https://checkout.stripe.test/pay/2');

    $purchase = TopUpPurchase::query()->sole();
    expect($purchase->only(['status', 'method', 'price_minor', 'currency', 'amount', 'save_card', 'provider_ref']))
        ->toBe(['status' => 'open', 'method' => 'card', 'price_minor' => 5000, 'currency' => 'SAR', 'amount' => 600, 'save_card' => true, 'provider_ref' => 'cs_test_2'])
        ->and(purchaseLots($this->academy->id))->toBe(0);
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/checkout/sessions') && $r['mode'] === 'payment' && $r['customer'] === 'cus_noor'
        && $r['metadata']['purchase'] === $purchase->id && $r['metadata']['academy'] === $this->academy->id && $r['metadata']['pack'] === $this->pack->id
        && $r['payment_intent_data']['setup_future_usage'] === 'off_session' && $r['line_items'][0]['price_data']['unit_amount'] === 5000
        && $r->header('Idempotency-Key')[0] === 'topup-'.$purchase->id);
    // The customer is made once.
    app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id);
    expect(collect($this->stripe->calls)->filter(fn ($c) => $c === 'POST /v1/customers')->count())->toBe(1)
        ->and(DB::connection('pgsql_platform')->table('platform_customers')->value('customer_ref'))->not->toContain('cus_noor');

    // No price in the academy's currency, Stripe not set up, a teacher, or the platform team signed in as the owner: refused.
    $usdOnly = TopUpPack::query()->create(['limit' => 'video_minutes', 'amount' => 300, 'name_ar' => 'صغيرة', 'name_en' => 'Small', 'prices' => ['USD' => 900]]);
    expect(fn () => app(StartTopUpCheckout::class)->handle($usdOnly->id, false, $this->owner->id))->toThrow(ValidationException::class, 'SAR');
    config(['billing.payments.stripe.platform_secret' => null]);
    expect(fn () => app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id))->toThrow(ValidationException::class);
    config(['billing.payments.stripe.platform_secret' => 'sk_test_platform']);
    $this->actingAs($this->teacher);
    expect(fn () => app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->teacher->id))->toThrow(AuthorizationException::class);
    $this->actingAs($this->owner);
    AuditActor::use(new AuditActor('platform', $this->platformOwner->id, 'سلمى', onBehalfOf: $this->owner->id));
    expect(fn () => app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id))->toThrow(ValidationException::class);
    expect(TopUpPurchase::query()->count())->toBe(2);
});

it('adds the lot only when Stripe says paid: a signed webhook, once, however often it comes', function () {
    Event::fake([RechargeSucceeded::class]);
    $purchase = app(StartTopUpCheckout::class)->handle($this->pack->id, true, $this->owner->id)['purchase']->fresh();

    // Not signed, signed with another secret, or signed too long ago: refused, and nothing kept.
    $this->postJson('http://lms.test/webhooks/platform/stripe', sessionEvent('evt_1', $purchase))->assertStatus(401);
    deliverStripeEvent(sessionEvent('evt_1', $purchase), 'whsec_other')->assertStatus(401);
    expect(PlatformWebhookItem::query()->count())->toBe(0);

    // Signed, but Stripe says the page is still open: kept, nothing added.
    deliverStripeEvent(sessionEvent('evt_1', $purchase))->assertOk();
    expect($purchase->fresh()->status)->toBe('open')->and(purchaseLots($this->academy->id))->toBe(0);

    // Paid: the lot (12 months), the receipt and the card, once.
    $this->stripe->session = 'paid';
    deliverStripeEvent(sessionEvent('evt_2', $purchase))->assertOk();
    deliverStripeEvent(sessionEvent('evt_2', $purchase))->assertOk();
    deliverStripeEvent(sessionEvent('evt_3', $purchase, 'checkout.session.async_payment_succeeded'))->assertOk();
    app(SettleTopUpPayment::class)->handle($purchase->fresh());

    $purchase->refresh();
    $state = app(Meters::class)->state($this->academy->id, Meter::VideoMinutes);
    expect($purchase->status)->toBe('paid')->and($purchase->payment_ref)->toBe('pi_checkout_1')
        ->and(purchaseLots($this->academy->id))->toBe(1)
        ->and($state->bought)->toBe(600)
        ->and($state->lots[0]['expires_at']->toDateString())->toBe('2027-11-10')
        ->and(PlatformReceipt::query()->count())->toBe(1)
        ->and(PlatformWebhookItem::query()->count())->toBe(3);
    $card = PlatformPaymentMethod::query()->sole();
    expect($card->only(['brand', 'last4', 'exp_month', 'exp_year']))->toBe(['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030])
        ->and($card->toArray())->not->toHaveKeys(['customer_ref', 'method_ref'])
        ->and(DB::connection('pgsql_platform')->table('platform_payment_methods')->value('method_ref'))->not->toContain('pm_card_1');
    Event::assertDispatchedTimes(RechargeSucceeded::class, 1);

    // The database holds one lot a purchase, and a purchase answers once.
    expect(fn () => app(Meters::class)->addLot($this->academy->id, Meter::VideoMinutes, Ledger::PURCHASE, 600, CarbonImmutable::now()->addYear(), ['ref_type' => 'purchase', 'ref_id' => $purchase->id]))->toThrow(QueryException::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('topup_purchases')->where('id', $purchase->id)->update(['status' => 'failed']))->toThrow(QueryException::class);
});

it('says where the payment stands on the way back, and adds it when Stripe already has it', function () {
    Filament::setCurrentPanel('academy');
    $purchase = app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id)['purchase'];

    Livewire::withQueryParams(['purchase' => $purchase->id])->test(UsagePage::class)
        ->assertSee(__('platform::recharge.back.confirming.title'));
    Livewire::withQueryParams(['purchase' => $purchase->id, 'cancelled' => '1'])->test(UsagePage::class)
        ->assertSee(__('platform::recharge.back.cancelled.title'));

    $this->stripe->session = 'paid';
    Livewire::withQueryParams(['purchase' => $purchase->id])->test(UsagePage::class)
        ->assertSee(__('platform::recharge.back.paid.title'))
        ->assertSee('R-2026-000001')
        ->assertSee(__('platform::recharge.purchases.title'));
    expect($purchase->fresh()->status)->toBe('paid')
        ->and(DB::table('notifications')->where('user_id', $this->owner->id)->where('template_key', 'plans.recharge_succeeded')->value('title'))->toContain('600 دقيقة');

    // Another academy's purchase is not this page's to tell.
    $other = $this->createAcademy(['slug' => 'other']);
    $foreign = TopUpPurchase::query()->create(['academy_id' => $other->id, 'pack_id' => $this->pack->id, 'limit' => 'video_minutes', 'amount' => 600, 'price_minor' => 5000, 'currency' => 'SAR', 'method' => 'card']);
    Livewire::withQueryParams(['purchase' => $foreign->id])->test(UsagePage::class)->assertDontSee(__('platform::recharge.back.confirming.title'));
});

it('numbers the platform\'s receipts by year under a lock, never changes them, and shows them only to the academy and the console', function () {
    $this->stripe->session = 'paid';
    foreach ([1, 2] as $_) {
        $purchase = app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id)['purchase'];
        app(SettleTopUpPayment::class)->handle($purchase->fresh());
    }
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-02 09:00', 'UTC'));
    $next = app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id)['purchase'];
    app(SettleTopUpPayment::class)->handle($next->fresh());

    expect(PlatformReceipt::query()->orderBy('created_at')->orderBy('number')->pluck('number')->all())->toBe(['R-2026-000001', 'R-2026-000002', 'R-2027-000001']);
    $receipt = PlatformReceipt::query()->where('number', 'R-2026-000001')->sole();
    expect($receipt->only(['price_minor', 'currency', 'method', 'amount', 'pack_name_ar']))->toBe(['price_minor' => 5000, 'currency' => 'SAR', 'method' => 'card', 'amount' => 600, 'pack_name_ar' => 'متوسطة'])
        ->and($receipt->seller)->toBe((string) config('privacy.platform_name'))
        ->and(fn () => DB::connection('pgsql_platform')->table('platform_receipts')->where('id', $receipt->id)->update(['price_minor' => 1]))->toThrow(QueryException::class);

    // The owner reads it (printable); a teacher may not; the console reads every academy's.
    $this->get('http://noor.lms.test/platform/receipts/'.$receipt->id)->assertOk()->assertSee('R-2026-000001')->assertSee('متوسطة')->assertSee(__('platform::recharge.receipt.print'));
    $this->actingAs($this->teacher)->get('http://noor.lms.test/platform/receipts/'.$receipt->id)->assertForbidden();
    $this->actingAs($this->owner)->get('http://noor.lms.test/platform/receipts/'.Str::uuid7())->assertNotFound();
    $this->clearTenant();
    $this->actingAs($this->platformOwner, 'platform')->get('/console/receipts/'.$receipt->id)->assertOk()->assertSee('R-2026-000001');
});

it('recharges automatically below the threshold, at most so many times a month', function () {
    Event::fake([RechargeSucceeded::class, RechargeFailed::class, AutoRechargeTurnedOff::class]);
    autoRechargeOn(threshold: 50, cap: 2);
    $id = $this->academy->id;

    // Above the threshold: nothing.
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::NOTHING);

    // Below it: the pack is bought on the saved card, with a key that never charges twice, and settled through Stripe's answer.
    app(Meters::class)->consume($id, Meter::VideoMinutes, 60, ['subject' => 'lesson']);
    app(MeteringSweep::class)->academy($id);
    $auto = TopUpPurchase::query()->where('method', 'auto')->sole();
    expect($auto->status)->toBe('paid')->and($auto->provider_ref)->toStartWith('pi_auto_')
        ->and(app(Meters::class)->state($id, Meter::VideoMinutes)->available())->toBe(640)
        ->and($auto->receipt?->method)->toBe('auto');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payment_intents') && $r['off_session'] === 'true' && $r['confirm'] === 'true'
        && $r['customer'] === 'cus_noor' && $r['payment_method'] === 'pm_card_1' && $r['amount'] === 5000 && $r->header('Idempotency-Key')[0] === 'auto-'.$auto->id);
    Event::assertDispatched(RechargeSucceeded::class, fn (RechargeSucceeded $e) => $e->method === 'auto' && $e->amount === 600 && $e->price->minor === 5000);

    // Twice more below the threshold: the second buys, the third is over the month's cap.
    app(Meters::class)->consume($id, Meter::VideoMinutes, 620, ['subject' => 'lesson']);
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::BUY);
    app(Meters::class)->consume($id, Meter::VideoMinutes, 600, ['subject' => 'lesson']);
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::CAPPED)
        ->and(TopUpPurchase::query()->where('method', 'auto')->where('status', 'paid')->count())->toBe(2);

    // Next month the cap starts again.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-02 09:00', 'UTC'));
    app(Meters::class)->consume($id, Meter::VideoMinutes, 150, ['subject' => 'lesson']);
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::BUY);
});

it('tries a soft decline once more a day later, then turns automatic recharge off and tells the staff', function () {
    autoRechargeOn(threshold: 50, cap: 3);
    $id = $this->academy->id;
    app(Meters::class)->consume($id, Meter::VideoMinutes, 60, ['subject' => 'lesson']);
    $this->stripe->charge = 'declined';
    $this->stripe->decline = 'insufficient_funds';

    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::BUY);
    $first = TopUpPurchase::query()->where('method', 'auto')->sole();
    expect($first->status)->toBe('failed')->and($first->failure)->toBe(CardDecline::InsufficientFunds->value)
        ->and(AutoRecharge::for($id, Meter::VideoMinutes)->enabled)->toBeTrue()
        ->and(DB::table('notifications')->where('template_key', 'plans.recharge_failed')->value('body'))->toContain('بعد يوم');

    // Within the day: it waits.
    $this->travel(12)->hours();
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::WAIT);

    // A day later: once more; declined again, automatic recharge turns off with a notice, and nothing is tried after.
    $this->travel(13)->hours();
    expect(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::RETRY);
    $retry = TopUpPurchase::query()->where('retry_of', $first->id)->sole();
    $setting = AutoRecharge::for($id, Meter::VideoMinutes);
    expect($retry->attempt)->toBe(2)->and($retry->status)->toBe('failed')
        ->and($setting->enabled)->toBeFalse()->and($setting->off_reason)->toBe('declined')
        ->and(DB::table('notifications')->where('template_key', 'plans.recharge_failed')->count())->toBe(2)
        ->and(DB::table('notifications')->where('template_key', 'plans.recharge_failed')->orderByDesc('created_at')->value('body'))->toContain('توقّف الشحن التلقائي')
        ->and(app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes))->toBe(AutoRechargeRules::NOTHING)
        ->and(purchaseLots($id))->toBe(0)
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.auto_recharge_off')->count())->toBe(1);

    // A hard decline (the card is done) turns it off at once and lets the card go.
    $this->actingAs($this->owner);
    app(SaveAutoRecharge::class)->handle(Meter::VideoMinutes, ['enabled' => true, 'pack_id' => $this->pack->id, 'threshold' => 50, 'monthly_cap' => 3], $this->owner->id);
    $this->stripe->decline = 'expired_card';
    $this->travel(2)->days();
    app(ChargeAutoTopUp::class)->handle($id, Meter::VideoMinutes);
    expect(AutoRecharge::for($id, Meter::VideoMinutes)->enabled)->toBeFalse()
        ->and(PlatformPaymentMethod::liveFor($id))->toBeNull()
        ->and(PlatformPaymentMethod::query()->value('removed_reason'))->toBe('declined');
});

it('lets only the owner and admins set automatic recharge and remove the card, never while signed in as someone, audited', function () {
    // No card yet: it cannot be turned on (it can be saved off).
    expect(fn () => app(SaveAutoRecharge::class)->handle(Meter::VideoMinutes, ['enabled' => true, 'pack_id' => $this->pack->id, 'threshold' => 50, 'monthly_cap' => 2], $this->owner->id))
        ->toThrow(ValidationException::class);
    autoRechargeOn();
    expect(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.auto_recharge_saved')->value('data'))->toContain('"auto_recharge": "on"')->not->toContain('pm_');

    $this->actingAs($this->teacher);
    expect(fn () => app(RemovePlatformCard::class)->handle())->toThrow(AuthorizationException::class);
    $this->actingAs($this->owner);
    AuditActor::use(new AuditActor('platform', $this->platformOwner->id, 'سلمى', onBehalfOf: $this->owner->id));
    expect(fn () => app(RemovePlatformCard::class)->handle())->toThrow(ValidationException::class)
        ->and(fn () => app(SaveAutoRecharge::class)->handle(Meter::VideoMinutes, ['enabled' => false, 'pack_id' => $this->pack->id, 'threshold' => 50, 'monthly_cap' => 2], $this->owner->id))->toThrow(ValidationException::class);
    AuditActor::forget();

    // The card's removal turns every automatic recharge off and lets it go on Stripe too.
    Filament::setCurrentPanel('academy');
    Livewire::test(UsagePage::class)->assertSee('4242')->assertSee(__('platform::recharge.auto.title'))
        ->callAction('removeCard')->assertHasNoActionErrors();
    expect(PlatformPaymentMethod::liveFor($this->academy->id))->toBeNull()
        ->and(AutoRecharge::for($this->academy->id, Meter::VideoMinutes)->enabled)->toBeFalse()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.platform_card_removed')->value('data'))->toContain('4242');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payment_methods/pm_card_1/detach'));
});

it('gives a transfer the platform team turned on a receipt of what arrived', function () {
    $topUp = app(RequestTopUp::class)->handle($this->pack->id, 'By transfer', $this->owner->id);
    $this->clearTenant();
    app(ActivateTopUp::class)->handle($this->platformOwner, $topUp->id, Money::of(4800, 'SAR'));

    $receipt = PlatformReceipt::query()->sole();
    expect($receipt->method)->toBe('transfer')->and($receipt->price_minor)->toBe(4800)
        ->and(TopUpPurchase::query()->sole()->only(['status', 'topup_id']))->toBe(['status' => 'paid', 'topup_id' => $topUp->id]);
});

it('reads the console\'s revenue in each currency on its own, the unused paid balance and the failed recharges', function () {
    $this->stripe->session = 'paid';
    $purchase = app(StartTopUpCheckout::class)->handle($this->pack->id, false, $this->owner->id)['purchase'];
    app(SettleTopUpPayment::class)->handle($purchase->fresh());
    app(Meters::class)->consume($this->academy->id, Meter::VideoMinutes, 150, ['subject' => 'lesson']);   // 100 from the allowance, 50 from the pack

    // A second academy in dollars, by transfer.
    $usd = $this->createAcademy(['slug' => 'dollars', 'base_currency' => 'USD']);
    TopUpPurchase::query()->create(['academy_id' => $usd->id, 'pack_id' => $this->pack->id, 'limit' => 'video_minutes', 'amount' => 600, 'price_minor' => 1400, 'currency' => 'USD', 'method' => 'transfer', 'status' => 'paid', 'paid_at' => now()]);
    app(Receipts::class)->issue(TopUpPurchase::query()->where('academy_id', $usd->id)->sole());
    TopUpPurchase::query()->create(['academy_id' => $this->academy->id, 'pack_id' => $this->pack->id, 'limit' => 'video_minutes', 'amount' => 600, 'price_minor' => 5000, 'currency' => 'SAR', 'method' => 'auto', 'status' => 'failed', 'failure' => 'declined', 'failed_at' => now()]);

    $report = app(RechargeReport::class);
    expect($report->revenueByCurrency(null))->toBe(['SAR' => 5000, 'USD' => 1400])
        ->and($report->sold(null)['video_minutes'])->toBe(['purchases' => 2, 'amount' => 1200])
        ->and($report->unused()['video_minutes'])->toBe(['left' => 550, 'expiring' => 0])
        ->and(count($report->failed(null)))->toBe(1);

    $this->clearTenant();
    Filament::setCurrentPanel('console');
    $this->actingAs($this->platformOwner, 'platform');
    app()->setLocale('en');
    Livewire::test(RechargeRevenuePage::class)->assertOk()
        ->assertSee(Money::of(5000, 'SAR')->format('en'))->assertSee(Money::of(1400, 'USD')->format('en'))
        ->assertSee('R-2026-000001')->assertSee(__('platform::recharge.declines.declined'))
        ->call('showPeriod', 'all')->assertSet('period', 'all');

    // Owners only.
    $helper = PlatformAdmin::query()->create(['name' => 'Omar', 'email' => 'omar@platform.test', 'password' => 'a-long-password']);
    $this->actingAs($helper, 'platform');
    expect(RechargeRevenuePage::canAccess())->toBeFalse();
});
