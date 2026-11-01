<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\CouponPurpose;
use Modules\Billing\Contracts\DTOs\PersonalCoupon;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The payer reads the invoice before paying and types a coupon on it (docs/
 * architecture/02, section 27, J3): lines, discounts and VAT on the signed pay
 * page and in the apps; a coupon only on a course's own block invoice with
 * nothing paid, no proof waiting and no payment page open, issued again with
 * it; the trial's offer stays once on; back from the gateway to the portal
 * when the payment began there. The fixture's guardian pays an SAR 400 block.
 */

const PAY_API = 'http://noor.lms.test/api/v1/';

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->findOrFail(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->token = $this->guardian->createToken('phone')->plainTextToken;
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->actingAs($this->admin, 'web');
    app(SaveCoupon::class)->handle(null, ['code' => 'WELCOME10', 'name' => 'Welcome', 'kind' => 'percent', 'percent' => 10, 'blocks' => 1], $this->admin->id);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function payApi(string $method, string $path, array $data = [], ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();
    $response = test()->withToken($token ?? test()->token)
        ->withHeaders(['Accept-Language' => 'ar', 'Idempotency-Key' => 'k-'.bin2hex(random_bytes(6))])->json($method, PAY_API.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

it('shows the payer the invoice line by line, the VAT and whether a coupon can be typed', function () {
    $data = payApi('GET', "family/invoices/{$this->invoice->id}")->assertOk()->json('data');

    expect($data)->toMatchArray(['id' => $this->invoice->id, 'status' => 'issued', 'currency' => 'SAR', 'coupon' => null])
        ->and(array_column($data['lines'], 'kind'))->toBe(['block'])
        ->and($data['total']['amount_minor'])->toBe(40000)
        ->and($data['outstanding']['amount_minor'])->toBe(40000)
        ->and($data['vat'])->toMatchArray(['rate' => 0, 'included' => true, 'amount_minor' => 0])
        ->and($data['coupon_entry'])->toBe(['open' => true, 'why_not' => null])
        ->and($data['pay_url'])->toContain('/pay/invoice/'.$this->invoice->id);
    // Not the payer: not found.
    $stranger = User::factory()->withRole(Role::Guardian)->create()->createToken('phone')->plainTextToken;
    payApi('GET', "family/invoices/{$this->invoice->id}", token: $stranger)->assertNotFound();
});

it('puts a coupon on before paying by issuing the invoice again, and takes it off the same way', function () {
    $applied = payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => ' welcome10 '])->assertOk()->json();

    $new = $applied['data'];
    expect($new['id'])->not->toBe($this->invoice->id)
        ->and($new['total']['amount_minor'])->toBe(36000)
        ->and(array_column($new['lines'], 'kind'))->toBe(['block', 'discount'])
        ->and($new['coupon'])->toBe(['code' => 'WELCOME10', 'personal' => false])
        ->and($applied['message'])->toContain('WELCOME10')
        ->and($this->invoice->refresh()->status)->toBe('void');

    // A second coupon waits until this one is off.
    expect(payApi('POST', "family/invoices/{$new['id']}/coupon", ['code' => 'WELCOME10'])->assertUnprocessable()->json('errors.coupon.0'))->toContain('أزيلوه أولًا');

    $removed = payApi('DELETE', "family/invoices/{$new['id']}/coupon")->assertOk()->json('data');
    expect($removed['total']['amount_minor'])->toBe(40000)->and($removed['coupon'])->toBeNull()
        ->and(Invoice::query()->find($new['id'])->status)->toBe('void');
});

it('says why a coupon cannot go on, in the payer\'s words and with what to do', function () {
    expect(payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => 'NOPE99'])->assertUnprocessable()->json('errors.coupon.0'))->toContain('تأكّدوا من كتابته');

    app(SaveCoupon::class)->handle(null, ['code' => 'OLD20', 'name' => 'Old', 'kind' => 'percent', 'percent' => 20, 'blocks' => 1, 'ends_on' => '2026-10-01'], $this->admin->id);
    expect(payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => 'OLD20'])->assertUnprocessable()->json('errors.coupon.0'))->toContain('انتهت مدة');

    // A transfer receipt waiting: not now, and the invoice stays as it is.
    DB::table('manual_payment_proofs')->insert(['id' => (string) Str::uuid(), 'academy_id' => $this->academy->id, 'invoice_id' => $this->invoice->id, 'payer_id' => $this->guardian->id,
        'channel' => 'family', 'method' => 'bank_transfer', 'amount_minor' => 40000, 'currency' => 'SAR', 'paid_on' => now()->toDateString(), 'file_path' => 'x', 'file_name' => 'x.jpg',
        'mime' => 'image/jpeg', 'size' => 1, 'created_at' => now(), 'updated_at' => now()]);
    expect(payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => 'WELCOME10'])->assertUnprocessable()->json('errors.coupon.0'))->toContain('قيد المراجعة')
        ->and(payApi('GET', "family/invoices/{$this->invoice->id}")->json('data.coupon_entry'))->toMatchArray(['open' => false])
        ->and($this->invoice->refresh()->status)->toBe('issued');
});

it('keeps the trial\'s offer on once it is on: it is not taken off and lost', function () {
    $offer = app(BillingApi::class)->issuePersonalCoupon(new PersonalCoupon(CouponPurpose::TrialOffer, $this->guardian->id, 1000, CarbonImmutable::now()->addDays(3), 'Trial offer'));
    // Someone else's offer reads as no such code.
    $other = User::factory()->withRole(Role::Guardian)->create();
    $theirs = app(BillingApi::class)->issuePersonalCoupon(new PersonalCoupon(CouponPurpose::TrialOffer, $other->id, 2000, CarbonImmutable::now()->addDays(3), 'Trial offer'));
    expect(payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => $theirs->code])->assertUnprocessable()->json('errors.coupon.0'))->toContain('لا يوجد كوبون');

    $new = payApi('POST', "family/invoices/{$this->invoice->id}/coupon", ['code' => $offer->code])->assertOk()->json('data');
    expect($new['coupon'])->toBe(['code' => $offer->code, 'personal' => true]);

    expect(payApi('DELETE', "family/invoices/{$new['id']}/coupon")->assertUnprocessable()->json('errors.coupon.0'))->toContain('حتى لا تفقدوه');
});

it('shows lines and VAT on the signed pay page and takes a coupon there, sending the payer to the new invoice', function () {
    $url = URL::temporarySignedRoute('billing.pay', now()->addDay(), ['invoice' => $this->invoice->id, 'u' => $this->guardian->id], absolute: false);
    app('auth')->forgetGuards();
    $page = $this->get('http://noor.lms.test'.$url)->assertOk();
    $page->assertSee('تفاصيل الفاتورة')->assertSee('لديكم كوبون خصم؟');

    preg_match('#action="([^"]*/coupon\?[^"]*)"#', $page->getContent(), $m);
    expect($m)->not->toBeEmpty();
    $back = $this->post('http://noor.lms.test'.html_entity_decode($m[1]), ['code' => 'WELCOME10'])->assertRedirect();
    $this->actAsTenant($this->academy);
    $new = Invoice::query()->where('status', 'issued')->latest('issued_at')->first();
    expect($back->headers->get('Location'))->toContain('/pay/invoice/'.$new->id)
        ->and($new->total_minor)->toBe(36000);
    $this->get($back->headers->get('Location'))->assertOk()->assertSee('WELCOME10');
});

it('brings the payer back to the portal after the gateway when the payment began in the portal or the app', function () {
    app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Card', 'mode' => 'test'], $this->admin->id);
    $checkout = payApi('POST', "family/invoices/{$this->invoice->id}/checkout", ['return' => 'app'])->assertCreated()->json('checkout_id');
    $row = PaymentCheckout::query()->findOrFail($checkout);
    expect($row->return_to)->toBe('app');

    $back = URL::temporarySignedRoute('billing.pay.return', now()->addHour(), ['checkout' => $checkout], absolute: false);
    app('auth')->forgetGuards();
    $to = $this->get('http://noor.lms.test'.$back)->assertRedirect()->headers->get('Location');
    expect($to)->toContain('/pay/return?')->toContain('invoice='.$this->invoice->id)->toContain('checkout='.$checkout)->toContain('from=app');
    $this->actAsTenant($this->academy);

    // The portal reads where the payment stands, signed in.
    expect(payApi('GET', "family/invoices/{$this->invoice->id}?checkout={$checkout}")->assertOk()->json('checkout.status'))->toBe('open');
    // Without a hint, the academy's own pay page as before.
    $plain = payApi('POST', "family/invoices/{$this->invoice->id}/checkout")->assertCreated()->json('checkout_id');
    expect(PaymentCheckout::query()->findOrFail($plain)->return_to)->toBeNull();
});
