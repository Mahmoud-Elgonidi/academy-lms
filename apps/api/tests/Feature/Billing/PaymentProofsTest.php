<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Resources\Invoices\ListInvoices;
use Modules\Billing\Filament\Resources\Invoices\ViewInvoice;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\PayLinks;
use Modules\Communication\Models\Notification;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Paying by hand with a proof (docs/architecture/03, section 5, "يدوي"):
 * the payer transfers, sends the receipt, and the academy approves it,
 * which records the payment, or turns it down with a reason.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();                     // an unpaid invoice of 400 SAR, paid by the guardian
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = User::query()->findOrFail(DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id'));
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function receipt(string $name = 'receipt.jpg', string $mime = 'image/jpeg', int $kb = 180): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, $mime);
}

function sendProof(array $input = [], ?string $payerId = null, string $channel = 'family'): PaymentProof
{
    $input += ['method' => 'instapay', 'amount' => '400', 'paid_on' => '2026-10-05', 'reference' => 'IP-55120', 'note' => null, 'file' => receipt()];

    return app(SubmitPaymentProof::class)->handle(test()->invoice->refresh(), $payerId ?? test()->payer->id, $channel,
        $input['method'], $input['amount'], $input['paid_on'], $input['reference'], $input['note'], $input['file']);
}

it('keeps a proof waiting for the academy, its receipt on the private disk, and tells whoever records payments', function () {
    $proof = sendProof();

    expect($proof->status)->toBe('pending')->and($proof->money())->toEqual(Money::of(40000, 'SAR'))
        ->and($proof->paid_on->toDateString())->toBe('2026-10-05')->and($proof->reference)->toBe('IP-55120')
        ->and($proof->file_path)->toStartWith("academies/{$this->academy->id}/payment-proofs/{$this->invoice->id}/")
        ->and($this->invoice->refresh()->status)->toBe('issued')->and(Payment::query()->count())->toBe(0);
    Storage::disk('local')->assertExists($proof->file_path);

    $told = Notification::query()->where('template_key', 'billing.proof_received')->pluck('user_id')->all();
    expect($told)->toContain($this->admin->id, $this->accountant->id)->not->toContain($this->teacher->id, $this->payer->id)
        ->and(Notification::query()->where('user_id', $this->accountant->id)->where('template_key', 'billing.proof_received')->sole()->body)
        ->toContain('400')->toContain($this->invoice->number);
});

it('takes one waiting proof per invoice, up to what is left, with a receipt, from the payer only', function () {
    sendProof();
    expect(fn () => sendProof())->toThrow(ValidationException::class, __('billing::proofs.errors.waiting'));
    PaymentProof::query()->update(['status' => 'rejected', 'decided_at' => now(), 'decided_by' => $this->admin->id]);

    $fails = fn (array $input) => fn () => sendProof($input);
    expect($fails(['amount' => '400.01']))->toThrow(ValidationException::class)
        ->and($fails(['amount' => '0']))->toThrow(ValidationException::class)
        ->and($fails(['method' => 'cash']))->toThrow(ValidationException::class)
        ->and($fails(['paid_on' => '2026-10-09']))->toThrow(ValidationException::class, __('billing::proofs.errors.future'))
        ->and($fails(['paid_on' => '2026-08-01']))->toThrow(ValidationException::class, __('billing::proofs.errors.too_early'))
        ->and($fails(['file' => receipt('song.mp3', 'audio/mpeg')]))->toThrow(ValidationException::class)
        ->and($fails(['file' => receipt(kb: 11 * 1024)]))->toThrow(ValidationException::class)
        ->and($fails(['file' => null]))->toThrow(ValidationException::class);

    $stranger = User::factory()->withRole(Role::Guardian)->create();
    expect(fn () => sendProof([], $stranger->id))->toThrow(AuthorizationException::class);
    expect(fn () => sendProof([], $this->student->id))->toThrow(AuthorizationException::class);
    expect(sendProof(['amount' => '150.5', 'file' => receipt('bank.pdf', 'application/pdf')])->amount_minor)->toBe(15050);
});

it('records the payment when approved, with what arrived: the invoice is paid and the lessons are confirmed', function () {
    $proof = sendProof();

    $invoice = app(ApprovePaymentProof::class)->handle($proof->id, Money::of(40000, 'SAR'), 'instapay', 'IP-55120', CarbonImmutable::now(), null, $this->accountant->id);

    $payment = Payment::query()->sole();
    expect($invoice->status)->toBe('paid')
        ->and($proof->refresh()->status)->toBe('approved')->and($proof->payment_id)->toBe($payment->id)->and($proof->decided_by)->toBe($this->accountant->id)
        ->and($payment->method)->toBe('instapay')->and($payment->recorded_by)->toBe($this->accountant->id)->and($payment->reference)->toBe('IP-55120')
        ->and(app(EnrollmentApi::class)->find($this->booked->enrollmentId)->status->value)->toBe('active')
        // Paid in full: the "paid" message says it, not a second one about the proof.
        ->and(Notification::query()->where('user_id', $this->payer->id)->where('template_key', 'billing.invoice_paid')->exists())->toBeTrue()
        ->and(Notification::query()->where('user_id', $this->payer->id)->where('template_key', 'billing.proof_approved')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'billing.payment_recorded')->where('subject_id', $this->invoice->id)->exists())->toBeTrue();

    expect(fn () => app(ApprovePaymentProof::class)->handle($proof->id, Money::of(100, 'SAR'), 'instapay', null, CarbonImmutable::now(), null, $this->admin->id))
        ->toThrow(ValidationException::class, __('billing::proofs.errors.decided'));
});

it('approves less than the payer wrote when less arrived; the rest stays owed and another proof can follow', function () {
    $proof = sendProof();

    $invoice = app(ApprovePaymentProof::class)->handle($proof->id, Money::of(25000, 'SAR'), 'bank_transfer', null, CarbonImmutable::now()->subHour(), 'وصل 250 بس', $this->admin->id);

    expect($invoice->status)->toBe('issued')->and($invoice->outstanding())->toEqual(Money::of(15000, 'SAR'))
        ->and(Notification::query()->where('user_id', $this->payer->id)->where('template_key', 'billing.proof_approved')->sole()->body)->toContain('250')->toContain('150');
    expect(sendProof(['amount' => '150'])->status)->toBe('pending');
    expect(fn () => sendProof(['amount' => '160']))->toThrow(ValidationException::class);
});

it('turns a proof down with a reason the payer reads, keeps a line in the activity log, and lets them send another', function () {
    $proof = sendProof();
    expect(fn () => app(RejectPaymentProof::class)->handle($proof->id, '  ', $this->admin->id))->toThrow(ValidationException::class);

    app(RejectPaymentProof::class)->handle($proof->id, 'المبلغ لسه ماوصلش الحساب.', $this->admin->id);

    expect($proof->refresh()->status)->toBe('rejected')->and($proof->decision_note)->toBe('المبلغ لسه ماوصلش الحساب.')
        ->and(Payment::query()->count())->toBe(0)->and($this->invoice->refresh()->status)->toBe('issued')
        ->and(DB::table('audit_logs')->where('action', 'billing.proof_rejected')->where('subject_id', $this->invoice->id)->exists())->toBeTrue();
    $told = Notification::query()->where('user_id', $this->payer->id)->where('template_key', 'billing.proof_rejected')->sole();
    expect($told->body)->toContain('المبلغ لسه ماوصلش الحساب.')->toContain('/pay/invoice/');
    expect(fn () => app(RejectPaymentProof::class)->handle($proof->id, 'تاني', $this->admin->id))->toThrow(ValidationException::class, __('billing::proofs.errors.decided'));
    expect(sendProof()->status)->toBe('pending');
});

it('voids no invoice while its proof waits; a proof on an invoice paid another way cannot be approved, only turned down', function () {
    $proof = sendProof();
    expect(fn () => app(VoidInvoice::class)->handle($this->invoice->id, 'خطأ في الباقة', $this->admin->id))
        ->toThrow(ValidationException::class, __('billing::billing.errors.void_proof'));
    expect($this->invoice->refresh()->status)->toBe('issued');

    app(RecordPayment::class)->handle($this->invoice->id, 'cash', Money::of(40000, 'SAR'), null, CarbonImmutable::now(), null, $this->admin->id);
    expect(fn () => app(ApprovePaymentProof::class)->handle($proof->id, Money::of(40000, 'SAR'), 'instapay', null, CarbonImmutable::now(), null, $this->admin->id))
        ->toThrow(ValidationException::class);
    expect($proof->refresh()->status)->toBe('pending');
    app(RejectPaymentProof::class)->handle($proof->id, 'اتدفعت كاش في الأكاديمية.', $this->admin->id);
    expect($proof->refresh()->status)->toBe('rejected');
});

it('shows the receipt to the payer and to whoever looks after the money, and to nobody else', function () {
    $proof = sendProof();
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $url = "http://noor.lms.test/billing/proofs/{$proof->id}/file";

    $this->actingAs($this->payer)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($this->accountant)->get($url)->assertOk();
    $this->actingAs($this->teacher)->get($url)->assertNotFound();
    $this->actingAs($this->student)->get($url)->assertNotFound();
    $this->actingAs($stranger)->get($url)->assertNotFound();
});

it('tells the payer\'s screens when a proof is sent or decided, and when anything is paid on their invoices', function () {
    config(['database.connections.proof_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('proof_listener')->getPdo();
    $pdo->exec('listen lms_live');
    $raised = function () use ($pdo) {
        $all = [];
        while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
            $all[] = $note['payload'];
        }

        return $all;
    };

    $proof = sendProof(['amount' => '100']);
    expect($raised())->toContain("payments:{$this->payer->id}");
    app(ApprovePaymentProof::class)->handle($proof->id, Money::of(10000, 'SAR'), 'instapay', null, CarbonImmutable::now(), null, $this->admin->id);
    expect($raised())->toContain("payments:{$this->payer->id}");

    // Recorded at the desk, without a proof: the payer's pages still hear it.
    app(RecordPayment::class)->handle($this->invoice->id, 'cash', Money::of(5000, 'SAR'), null, CarbonImmutable::now(), null, $this->admin->id);
    expect($raised())->toContain("payments:{$this->payer->id}");
});

it('lets the payer send a proof from their account where the academy says how to pay by hand, and shows where it stands', function () {
    $this->actingAs($this->payer);
    Filament\Facades\Filament::setCurrentPanel('family');

    Livewire\Livewire::test(FamilyBilling::class)->assertDontSee(__('billing::proofs.send.action'));
    BillingSettings::current()->fill(['manual_payment_note' => "InstaPay: noor@instapay\nIBAN EG00 1234"])->save();

    Livewire\Livewire::test(FamilyBilling::class)
        ->assertSee(__('billing::proofs.send.action'))
        ->mountAction('sendProof', arguments: ['invoice' => $this->invoice->id])
        ->assertSee('noor@instapay')
        ->setActionData(['method' => 'instapay', 'amount' => '400', 'paid_on' => '2026-10-05', 'reference' => 'IP-1', 'file' => receipt()])
        ->callMountedAction()
        ->assertNotified(__('billing::proofs.send.sent'))
        ->assertSee(__('billing::proofs.state.pending', ['amount' => Money::of(40000, 'SAR')->format(), 'date' => '5 October']))
        ->assertDontSee(__('billing::proofs.send.action'));
    expect(PaymentProof::query()->sole())->channel->toBe('family')->method->toBe('instapay');

    $this->actingAs(User::factory()->withRole(Role::Guardian)->create());
    Livewire\Livewire::test(FamilyBilling::class)
        ->mountAction('sendProof', arguments: ['invoice' => $this->invoice->id])->assertStatus(404);
});

it('puts proofs to review first in the back office, with the receipt, to approve or turn down', function () {
    $proof = sendProof();
    $this->actingAs($this->accountant);
    Filament\Facades\Filament::setCurrentPanel('academy');

    expect(InvoiceResource::getNavigationBadge())->toBe('1');
    Livewire\Livewire::test(ListInvoices::class)
        ->assertSet('activeTab', 'proofs')
        ->assertCanSeeTableRecords([$this->invoice])
        ->assertSee(__('billing::proofs.review.waiting'));

    Livewire\Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])
        ->assertSee(route('billing.proofs.file', ['proof' => $proof->id]), false)
        ->assertSee('IP-55120')
        ->assertActionHidden('recordPayment')
        ->callAction('approveProof', data: ['amount' => '400', 'method' => 'instapay', 'received_at' => '2026-10-05 09:00:00', 'reference' => 'IP-55120'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('billing::proofs.review.approved'))
        ->assertActionHidden('approveProof');
    expect($this->invoice->refresh()->status)->toBe('paid')->and($proof->refresh()->status)->toBe('approved');

    // A teacher has no business here.
    $this->actingAs($this->teacher);
    $this->clearTenant();
    $this->get("http://noor.lms.test/admin/invoices/{$this->invoice->id}")->assertForbidden();
});

it('turns a proof down from the invoice page with a reason', function () {
    $proof = sendProof();
    $this->actingAs($this->admin);
    Filament\Facades\Filament::setCurrentPanel('academy');

    Livewire\Livewire::test(ViewInvoice::class, ['record' => $this->invoice->id])
        ->callAction('rejectProof', data: ['reason' => ''])->assertHasActionErrors(['reason' => 'required'])
        ->setActionData(['reason' => 'الإيصال مش واضح'])->callMountedAction()
        ->assertNotified(__('billing::proofs.review.rejected'))
        ->assertSee('الإيصال مش واضح')
        ->assertActionVisible('recordPayment');
    expect($proof->refresh()->status)->toBe('rejected');
});

it('takes a proof from the invoice\'s signed pay link, without signing in', function () {
    BillingSettings::current()->fill(['manual_payment_note' => 'IBAN EG00 1234'])->save();
    $link = app(PayLinks::class)->invoice($this->invoice, $this->payer->id);
    $this->clearTenant();

    $page = $this->get($link)->assertOk()->assertSee('IBAN EG00 1234')->assertSee(__('billing::proofs.send.action'));
    preg_match('#action="([^"]*/pay/invoice/[^"]+/proof[^"]*)"#', $page->getContent(), $form);
    $url = html_entity_decode($form[1]);

    $this->post(preg_replace('/signature=[^&]+/', 'signature=forged', $url), [])->assertForbidden();
    $this->post($url, ['method' => 'wallet', 'amount' => '999', 'paid_on' => '2026-10-04', 'file' => receipt()])
        ->assertRedirect()->assertSessionHasErrors('amount');
    $sent = $this->post($url, ['method' => 'wallet', 'amount' => '400', 'paid_on' => '2026-10-04', 'reference' => 'VF-77', 'file' => receipt('shot.png', 'image/png')])
        ->assertRedirect()->assertSessionHas('proof_sent');
    $this->get($sent->headers->get('Location'))->assertOk()->assertSee(__('billing::proofs.send.sent'))
        ->assertDontSee('/proof?', false);

    $this->actAsTenant($this->academy);
    expect(PaymentProof::query()->sole())->channel->toBe('link')->payer_id->toBe($this->payer->id)->method->toBe('wallet');
});

it('takes a proof from the apps, says on each invoice where it stands, and replays a retried upload', function () {
    BillingSettings::current()->fill(['manual_payment_note' => 'InstaPay: noor@instapay'])->save();
    $app = function (User $user) {
        $this->actAsTenant($this->academy);
        $token = $user->createToken('phone')->plainTextToken;
        $this->clearTenant();
        app('auth')->forgetGuards();

        return $token;
    };
    $token = $app($this->payer);

    $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/invoices')->assertOk()
        ->assertJsonPath('manual.note', 'InstaPay: noor@instapay')->assertJsonPath('manual.takes_proofs', true)
        ->assertJsonPath('manual.methods.1', ['value' => 'instapay', 'label' => 'InstaPay'])
        ->assertJsonPath('data.0.proof', null)->assertJsonPath('data.0.can_send_proof', true);

    $send = fn (string $key, array $extra = []) => $this->withToken($token)->withHeaders(['Idempotency-Key' => $key, 'Accept-Language' => 'en'])
        ->post("/api/v1/family/invoices/{$this->invoice->id}/proofs", $extra + ['method' => 'instapay', 'amount' => '400', 'paid_on' => '2026-10-05', 'reference' => 'IP-9', 'file' => receipt()], ['Accept' => 'application/json']);
    $first = $send('proof-1')->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.paid_on', '2026-10-05')->json('data.id');
    $send('proof-1')->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $first);
    $send('proof-2')->assertUnprocessable()->assertJsonPath('errors.invoice.0', __('billing::proofs.errors.waiting', [], 'en'));

    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertJsonPath('data.0.proof.status', 'pending')->assertJsonPath('data.0.can_send_proof', false);

    $this->actAsTenant($this->academy);
    app(RejectPaymentProof::class)->handle($first, 'Not in the account yet', $this->admin->id);
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $this->clearTenant();
    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertJsonPath('data.0.proof.status', 'rejected')
        ->assertJsonPath('data.0.proof.reason', 'Not in the account yet')->assertJsonPath('data.0.can_send_proof', true);

    // A receipt is required, of the kinds a receipt comes in; someone else's invoice is not there.
    $send('proof-3', ['file' => null])->assertUnprocessable()->assertJsonValidationErrors('file');
    $send('proof-4', ['file' => receipt('a.exe', 'application/x-msdownload')])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->withToken($app($stranger))->post("/api/v1/family/invoices/{$this->invoice->id}/proofs", ['method' => 'instapay', 'amount' => '1', 'paid_on' => '2026-10-05', 'file' => receipt()], ['Accept' => 'application/json'])->assertNotFound();
});
