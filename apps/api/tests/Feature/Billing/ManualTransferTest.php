<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Models\Notification;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;

/*
 * The launch path (docs/architecture/03, section 5, "يدوي"): a family pays
 * by bank transfer and sends the receipt; the academy approves what actually
 * arrived, or turns it down. The money must land everywhere at once: the
 * invoice, each child's lessons, the revenue screen, the payer's messages
 * and the activity log. The fixture: a guardian with two children, Ziad
 * (Quran) and Maryam (Arabic), each on 8 lessons for 400 SAR (15% VAT
 * inside), first blocks paid; their next blocks on one family invoice of 800.
 */

beforeEach(function () {
    Storage::fake('local');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['name' => 'Hala']);
    $this->kids = User::factory()->withRole(Role::Student)->count(2)->sequence(['name' => 'Ziad'], ['name' => 'Maryam'])->create(['timezone' => 'Asia/Riyadh']);
    foreach ($this->kids as $kid) {
        app(PeopleApi::class)->linkGuardian($this->guardian->id, $kid->id);
    }
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '15:00', 'duration_min' => 360]);
    }
    BillingSettings::query()->create(['tax_rate_bp' => 1500, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-', 'manual_payment_note' => 'IBAN SA00 1234']);
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);

    $this->ziad = transferEnrol(0, $this->quran, 16);
    $this->maryam = transferEnrol(1, $this->arabic, 17);
    foreach ([$this->ziad, $this->maryam] as $course) {
        app(RecordPayment::class)->handle($course->invoice->id, 'bank_transfer', Money::of(40000, 'SAR'), 'T-1', CarbonImmutable::now(), null, $this->accountant->id);
    }
    $blocks = array_map(fn (EnrollmentBooked $b) => [transferCourse($b), 2, ...transferCourse($b)->subjectNames()], [$this->ziad, $this->maryam]);
    $this->family = app(IssueBlockInvoice::class)->together($blocks, $this->admin->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function transferEnrol(int $kid, string $subjectId, int $hour): EnrollmentBooked
{
    $t = test();
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse(sprintf('%s %02d:00', $first, $hour), 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $t->kids[$kid]->id, $times, 30, $t->admin->id);

    return app(EnrollStudent::class)->handle($batch, $t->kids[$kid]->id, $subjectId, new EnrollmentTerms($t->plan->id, $t->guardian->id, $t->gulf->id), $t->admin->id);
}

function transferCourse(EnrollmentBooked $booked): Subscription
{
    return Subscription::query()->with('plan')->where('enrollment_id', $booked->enrollmentId)->firstOrFail();
}

/** Minutes the course has left (free and booked). */
function transferMinutes(EnrollmentBooked $booked): int
{
    return app(CreditsApi::class)->balance(app(EnrollmentApi::class)->find($booked->enrollmentId)->creditAccountId)->remainingMin();
}

function transferProof(string $amount, string $method = 'bank_transfer', ?Invoice $invoice = null): PaymentProof
{
    $invoice = ($invoice ?? test()->family)->refresh();

    return app(SubmitPaymentProof::class)->handle($invoice, test()->guardian->id, 'family', $method, $amount, '2026-10-05', 'TRX-'.$amount, null,
        UploadedFile::fake()->create('transfer.jpg', 120, 'image/jpeg'));
}

it('pays a family invoice by transfer in two goes: each approval records what arrived, and the lessons come with the last', function () {
    expect([$this->family->subscription_id, $this->family->total_minor, $this->family->tax_minor])->toBe([null, 80000, 10435])
        ->and([transferMinutes($this->ziad), transferMinutes($this->maryam)])->toBe([240, 240]);

    // The payer says they sent 500; 300 arrived.
    $first = transferProof('500', 'instapay');
    expect(Notification::query()->where('template_key', 'billing.proof_received')->pluck('user_id')->all())->toContain($this->admin->id, $this->accountant->id);
    $invoice = app(ApprovePaymentProof::class)->handle($first->id, Money::of(30000, 'SAR'), 'instapay', 'TRX-500', CarbonImmutable::now(), 'وصل 300 بس', $this->accountant->id);

    expect([$invoice->status, $invoice->paid_minor, $invoice->outstanding()->minor])->toBe(['issued', 30000, 50000])
        ->and(Payment::query()->where('invoice_id', $this->family->id)->sole()->only(['method', 'amount_minor', 'recorded_by']))
        ->toBe(['method' => 'instapay', 'amount_minor' => 30000, 'recorded_by' => $this->accountant->id])
        // Nothing is granted while something is owed.
        ->and([transferMinutes($this->ziad), transferMinutes($this->maryam)])->toBe([240, 240])
        ->and([transferCourse($this->ziad)->block_no, transferCourse($this->maryam)->block_no])->toBe([1, 1]);
    $told = Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'billing.proof_approved')->sole();
    expect($told->body)->toContain('300')->toContain('500')->toContain($this->family->number);

    // What the payer sees in the app: 500 still owed, the proof approved, another can be sent.
    $token = $this->guardian->createToken('phone')->plainTextToken;
    $this->clearTenant();
    app('auth')->forgetGuards();
    $row = collect($this->withToken($token)->getJson('/api/v1/family/invoices')->assertOk()->json('data'))->firstWhere('id', $this->family->id);
    expect([$row['status'], $row['outstanding_minor'], $row['proof']['status'], $row['can_send_proof']])->toBe(['partly_paid', 50000, 'approved', true]);
    $this->actAsTenant($this->academy);

    // The rest arrives: the invoice is paid, and each child gets their own block.
    $second = transferProof('500');
    $paid = app(ApprovePaymentProof::class)->handle($second->id, Money::of(50000, 'SAR'), 'bank_transfer', null, CarbonImmutable::now(), null, $this->accountant->id);

    expect([$paid->status, $paid->paid_minor])->toBe(['paid', 80000])
        ->and([transferMinutes($this->ziad), transferMinutes($this->maryam)])->toBe([480, 480])
        ->and([transferCourse($this->ziad)->block_no, transferCourse($this->maryam)->block_no])->toBe([2, 2])
        ->and($second->refresh()->payment_id)->toBe(Payment::query()->where('invoice_id', $this->family->id)->where('amount_minor', 50000)->value('id'))
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'billing.proof_approved')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'billing.invoice_paid')->where('body', 'like', '%'.$this->family->number.'%')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'billing.payment_recorded')->where('subject_id', $this->family->id)->count())->toBe(2);

    // The revenue screen: the family's money shared between the children's courses, every breakdown adding up to what came in.
    $board = app(RevenueBoard::class);
    $period = $board->period('this_month');
    $summary = $board->summary($period, 'SAR');
    $by = $board->breakdown($period, 'SAR');
    $rows = fn (string $group) => collect($by[$group])->mapWithKeys(fn ($r) => [$r['key'] => $r['minor']])->all();
    expect($summary['collected']->minor)->toBe(160000)
        ->and($summary['outstanding']->minor)->toBe(0)
        ->and($rows('methods'))->toBe(['bank_transfer' => 130000, 'instapay' => 30000])
        ->and($rows('subjects'))->toEqualCanonicalizing([$this->quran => 80000, $this->arabic => 80000])
        ->and($rows('kinds'))->toEqualCanonicalizing(['new' => 80000, 'renewal' => 80000]);

    // Both receipts open for the payer and the accountant, for nobody else.
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    foreach ([$first, $second] as $proof) {
        $url = "http://noor.lms.test/billing/proofs/{$proof->id}/file";
        $this->actingAs($this->guardian)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->accountant)->get($url)->assertOk();
        $this->actingAs($stranger)->get($url)->assertNotFound();
        $this->actingAs($this->teacher)->get($url)->assertNotFound();
        $this->actingAs($this->kids[0])->get($url)->assertNotFound();
    }
});

it('takes no proof where nothing is owed: a cancelled invoice, or one paid online', function () {
    app(VoidInvoice::class)->handle($this->family->id, 'خطأ', $this->admin->id);
    expect(fn () => transferProof('800'))->toThrow(ValidationException::class, __('billing::billing.errors.not_payable'));

    // Ziad's next block on its own, paid on the gateway's page.
    $own = app(IssueBlockInvoice::class)->handle(transferCourse($this->ziad), 2, 'قرآن', 'Quran', $this->admin->id);
    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Demo', 'mode' => 'test'], $this->admin->id);
    $checkout = app(StartCheckout::class)->handle($own, $account, $this->guardian->id, 'family');
    DemoGateway::decide($checkout->provider_ref, pay: true);
    app(SettleCheckout::class)->handle($checkout);

    expect($own->refresh()->status)->toBe('paid')
        ->and(fn () => transferProof('400', invoice: $own))->toThrow(ValidationException::class, __('billing::billing.errors.not_payable'))
        ->and(PaymentProof::query()->count())->toBe(0);
});

it('cannot approve a waiting proof once the invoice was paid another way, and tells the payer why it was turned down', function () {
    $proof = transferProof('800');

    // The family also paid online meanwhile.
    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Demo', 'mode' => 'test'], $this->admin->id);
    $checkout = app(StartCheckout::class)->handle($this->family->refresh(), $account, $this->guardian->id, 'family');
    DemoGateway::decide($checkout->provider_ref, pay: true);
    app(SettleCheckout::class)->handle($checkout);

    expect(fn () => app(ApprovePaymentProof::class)->handle($proof->id, Money::of(80000, 'SAR'), 'bank_transfer', null, CarbonImmutable::now(), null, $this->accountant->id))
        ->toThrow(ValidationException::class, __('billing::billing.errors.not_payable'));
    expect(Payment::query()->where('invoice_id', $this->family->id)->count())->toBe(1)
        ->and([transferMinutes($this->ziad), transferMinutes($this->maryam)])->toBe([480, 480]);

    app(RejectPaymentProof::class)->handle($proof->id, 'اتدفعت أونلاين، هنرجعلك التحويل.', $this->accountant->id);
    expect($proof->refresh()->status)->toBe('rejected')
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'billing.proof_rejected')->sole()->body)->toContain('اتدفعت أونلاين');
});

it('approves no more than is left when part was paid at the desk while the proof waited', function () {
    $proof = transferProof('800');
    app(RecordPayment::class)->handle($this->family->id, 'cash', Money::of(30000, 'SAR'), null, CarbonImmutable::now(), null, $this->accountant->id);

    expect(fn () => app(ApprovePaymentProof::class)->handle($proof->id, Money::of(80000, 'SAR'), 'bank_transfer', null, CarbonImmutable::now(), null, $this->accountant->id))
        ->toThrow(ValidationException::class);
    expect($proof->refresh()->status)->toBe('pending');

    $paid = app(ApprovePaymentProof::class)->handle($proof->id, Money::of(50000, 'SAR'), 'bank_transfer', null, CarbonImmutable::now(), null, $this->accountant->id);
    expect([$paid->status, $paid->paid_minor])->toBe(['paid', 80000])
        ->and($proof->refresh()->status)->toBe('approved')
        ->and([transferMinutes($this->ziad), transferMinutes($this->maryam)])->toBe([480, 480]);
});
