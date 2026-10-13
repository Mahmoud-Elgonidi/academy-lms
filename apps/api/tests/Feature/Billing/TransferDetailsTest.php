<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SaveTransferDetails;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Billing\Filament\Pages\PaymentSettings;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\TransferDetails;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The academy's transfer details (docs/architecture/02, section 3): where a
 * family sends the money by hand, in the academy's own words, with a price
 * region's own text for the payers in it. Shown, escaped and with its line
 * breaks, wherever an invoice still waits to be paid; never once it is paid,
 * never in a child's own look, never another academy's.
 * Every account number here is made up.
 */

const TD_GULF = "Bank: Example Bank\nAccount holder: Noor Academy\nIBAN: SA00 0000 0000 0000 0000 0000";
const TD_ACADEMY = "InstaPay: noor@instapay\nVodafone Cash: 01000000000";

beforeEach(function () {
    $this->enrolStudentOnPlan();                     // an unpaid invoice of 400 SAR, paid by the guardian (Gulf region)
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = User::query()->findOrFail(DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id'));
    $this->gulf = PriceRegion::query()->sole();
    $this->egypt = PriceRegion::query()->create(['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'sort' => 1]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A token for the apps, as the person signs in on the academy's host. */
function transferDetailsApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

function transferDetailsPay(int $minor): void
{
    app(RecordPayment::class)->handle(test()->invoice->id, 'bank_transfer', Money::of($minor, 'SAR'), 'T-'.$minor, CarbonImmutable::now(), null, test()->admin->id);
}

it('keeps the academy\'s text and a region\'s own, as plain text, and audits that they changed without what they say', function () {
    $save = app(SaveTransferDetails::class);
    $save->handle("  InstaPay: noor@instapay\r\nVodafone Cash: 01000000000\n  ", [$this->gulf->id => TD_GULF, $this->egypt->id => '   ']);

    expect(BillingSettings::current()->manual_payment_note)->toBe(TD_ACADEMY)
        ->and($this->gulf->refresh()->transfer_details)->toBe(TD_GULF)
        ->and($this->egypt->refresh()->transfer_details)->toBeNull();
    $audit = DB::table('audit_logs')->where('action', 'billing.transfer_details_saved')->sole();
    expect(json_decode($audit->data, true))->toBe(['academy' => true, 'regions' => [$this->gulf->id]])
        ->and($audit->data)->not->toContain('IBAN')->not->toContain('instapay')
        ->and($audit->actor_id)->toBeNull();

    // The same again changes nothing and is not audited again; a region left out keeps its text.
    $save->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    $save->handle(TD_ACADEMY);
    expect(DB::table('audit_logs')->where('action', 'billing.transfer_details_saved')->count())->toBe(1)
        ->and($this->gulf->refresh()->transfer_details)->toBe(TD_GULF);

    // Emptied: the region's payers read the academy's text again.
    $save->handle(TD_ACADEMY, [$this->gulf->id => null]);
    expect($this->gulf->refresh()->transfer_details)->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'billing.transfer_details_saved')->count())->toBe(2);
});

it('refuses a text too long, markup, and a region that is not the academy\'s', function () {
    $save = app(SaveTransferDetails::class);
    $error = function (callable $call): array {
        try {
            $call();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };

    expect($error(fn () => $save->handle(str_repeat('ب', TransferDetails::MAX))))->toBe([])
        ->and($error(fn () => $save->handle(str_repeat('ب', TransferDetails::MAX + 1))))->toBe(['academy' => [__('billing::transfer.errors.too_long', ['max' => 1000])]])
        ->and($error(fn () => $save->handle('IBAN <b>SA00</b>')))->toBe(['academy' => [__('billing::transfer.errors.markup')]])
        ->and($error(fn () => $save->handle(null, [$this->gulf->id => '<script>alert(1)</script>'])))->toBe(["regions.{$this->gulf->id}" => [__('billing::transfer.errors.markup')]])
        ->and($error(fn () => $save->handle('<!-- hidden --> IBAN')))->toHaveKey('academy')
        ->and($error(fn () => $save->handle(null, ['not-a-region' => 'x'])))->toHaveKey('regions.not-a-region')
        ->and($error(fn () => $save->handle(null, ['0199a0a0-0000-7000-8000-000000000000' => 'x'])))->toHaveKey('regions.0199a0a0-0000-7000-8000-000000000000');
    // Comparing amounts is not markup.
    expect($error(fn () => $save->handle("Transfers < 5,000 SAR: InstaPay\nAbove: the bank <3")))->toBe([]);
    // Nothing refused was kept, nor half of it.
    expect($this->gulf->refresh()->transfer_details)->toBeNull();
});

it('gives each invoice its payer\'s region\'s words, else the academy\'s, only while something is left to pay', function () {
    $details = app(TransferDetails::class);
    expect($details->forInvoice($this->invoice))->toBeNull();

    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->egypt->id => 'Egypt only: 01000000000']);
    expect($details->forInvoice($this->invoice))->toBe(TD_ACADEMY)
        ->and($details->forPayer($this->payer->id))->toBe(TD_ACADEMY);

    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    expect($details->forInvoice($this->invoice))->toBe(TD_GULF)
        ->and($details->forRegion($this->egypt->id))->toBe('Egypt only: 01000000000')
        ->and($details->forPayer($this->payer->id))->toBe(TD_GULF);

    transferDetailsPay(10000);
    expect($details->forInvoice($this->invoice->refresh()))->toBe(TD_GULF);
    transferDetailsPay(30000);
    expect($this->invoice->refresh()->status)->toBe('paid')
        ->and($details->forInvoice($this->invoice))->toBeNull();
});

it('shows them on the payer\'s money screen and with the proof form, with a copy button, and not once the invoice is paid', function () {
    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    $this->actingAs($this->payer);
    Filament\Facades\Filament::setCurrentPanel('family');

    Livewire\Livewire::test(FamilyBilling::class)
        ->assertSeeInOrder([__('billing::transfer.title'), __('billing::transfer.copy'), 'Bank: Example Bank'])
        ->assertSee('IBAN: SA00 0000 0000 0000 0000 0000')
        ->assertDontSee('noor@instapay')                                  // the academy's text is not this payer's
        ->assertDontSee(__('billing::family.how_to_pay'))
        ->assertSee(__('billing::proofs.send.action'))
        ->mountAction('sendProof', arguments: ['invoice' => $this->invoice->id])
        // The form's own copy, after its heading (the page's is above the invoices).
        ->assertSeeInOrder([__('billing::proofs.send.title'), __('billing::transfer.title'), 'Account holder: Noor Academy', __('billing::proofs.send.method')]);

    transferDetailsPay(40000);
    Livewire\Livewire::test(FamilyBilling::class)
        ->assertDontSee('Example Bank')->assertDontSee(__('billing::transfer.title'))->assertDontSee(__('billing::proofs.send.action'));
});

it('offers no proof where the academy wrote no transfer details for the payer', function () {
    app(SaveTransferDetails::class)->handle(null, [$this->egypt->id => 'Egypt only: 01000000000']);
    $this->actingAs($this->payer);
    Filament\Facades\Filament::setCurrentPanel('family');

    Livewire\Livewire::test(FamilyBilling::class)
        ->assertDontSee(__('billing::transfer.title'))->assertDontSee('Egypt only')
        ->assertDontSee(__('billing::proofs.send.action'))->assertSee(__('billing::family.how_to_pay'));
});

it('says them on the signed pay page and the invoice document, escaped with their line breaks, until the invoice is paid', function () {
    // Written before the screens refused markup: still shown as the text it is, never as HTML.
    BillingSettings::current()->fill(['manual_payment_note' => "IBAN <b>SA00</b> & \"Co\"\nSTC Pay: 0500000000"])->save();
    $link = app(PayLinks::class)->invoice($this->invoice, $this->payer->id);
    $document = app(PayLinks::class)->document($this->invoice, $this->payer->id);
    $this->clearTenant();

    foreach ([$link, $document] as $url) {
        $page = $this->get($url)->assertOk()
            ->assertSee(__('billing::transfer.title'))->assertSee(__('billing::transfer.copy'))
            ->assertSee('IBAN &lt;b&gt;SA00&lt;/b&gt; &amp; &quot;Co&quot;'."\nSTC Pay: 0500000000", false)
            ->assertDontSee('<b>SA00</b>', false);
        expect($page->getContent())->toContain('data-transfer');
    }
    $this->get($link)->assertSee(__('billing::proofs.send.action'));

    $this->actAsTenant($this->academy);
    transferDetailsPay(40000);
    $this->clearTenant();
    $this->get($link)->assertOk()->assertDontSee('SA00')->assertDontSee(__('billing::transfer.title'))->assertDontSee(__('billing::proofs.send.action'));
    $this->get($document)->assertOk()->assertDontSee('SA00')->assertDontSee(__('billing::transfer.title'));
});

it('answers them on the family invoices endpoint, per invoice and for earlier apps, and none once paid', function () {
    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    $token = transferDetailsApp($this->payer);

    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertOk()
        ->assertJsonPath('data.0.transfer_details', TD_GULF)
        ->assertJsonPath('data.0.can_send_proof', true)
        ->assertJsonPath('manual.note', TD_GULF)
        ->assertJsonPath('manual.takes_proofs', true);

    $this->actAsTenant($this->academy);
    transferDetailsPay(40000);
    $this->clearTenant();
    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertOk()
        ->assertJsonPath('data.0.status', 'paid')
        ->assertJsonPath('data.0.transfer_details', null)
        ->assertJsonPath('data.0.can_send_proof', false);
});

it('answers no transfer details, nor proofs, where the academy wrote none', function () {
    $token = transferDetailsApp($this->payer);

    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertOk()
        ->assertJsonPath('data.0.transfer_details', null)
        ->assertJsonPath('data.0.can_send_proof', false)
        ->assertJsonPath('manual.note', null)
        ->assertJsonPath('manual.takes_proofs', false);
});

it('shows a child in the kids\' look no money and no transfer details', function () {
    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2018-03-01'), null);
    if (app(PeopleApi::class)->consentState($this->student->id)->waiting()) {
        app(GrantConsent::class)->handle($this->student->id, $this->payer->id, 'family');
    }
    app()->forgetInstance(FamilyView::class);
    expect(app(FamilyView::class)->stage($this->student->id)->value)->toBe('kids');

    $this->actingAs($this->student);
    Filament\Facades\Filament::setCurrentPanel('family');
    Livewire\Livewire::test(FamilyBilling::class)->assertDontSee('Example Bank')->assertDontSee(__('billing::transfer.title'));

    $this->withToken(transferDetailsApp($this->student))->getJson('/api/v1/family/invoices')->assertForbidden()
        ->assertJsonMissingPath('data');
});

it('never shows another academy\'s transfer details, nor lets it write a region of this one', function () {
    app(SaveTransferDetails::class)->handle(TD_ACADEMY);
    $this->actAsTenant($this->createAcademy(['slug' => 'huda']));
    app(SaveTransferDetails::class)->handle("Huda's bank: XX00 0000 0000");
    expect(fn () => app(SaveTransferDetails::class)->handle(null, [$this->gulf->id => 'taken over']))->toThrow(ValidationException::class);
    expect(app(TransferDetails::class)->academy())->toBe("Huda's bank: XX00 0000 0000");

    $this->actAsTenant($this->academy);
    expect($this->gulf->refresh()->transfer_details)->toBeNull()
        ->and(app(TransferDetails::class)->forInvoice($this->invoice))->toBe(TD_ACADEMY);
    $link = app(PayLinks::class)->invoice($this->invoice, $this->payer->id);
    $token = transferDetailsApp($this->payer);
    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertOk()
        ->assertJsonPath('data.0.transfer_details', TD_ACADEMY)->assertDontSee('Huda');
    $this->get($link)->assertOk()->assertSee('noor@instapay')->assertDontSee('Huda');

    // The pay link on the other academy's host finds no such invoice.
    expect($this->get(str_replace('//noor.', '//huda.', $link))->status())->toBeIn([403, 404]);
});

it('sends the invoice message with the pay link, which opens on where to transfer (the message itself goes to WhatsApp too: no account numbers in it)', function () {
    app(SaveTransferDetails::class)->handle(TD_ACADEMY, [$this->gulf->id => TD_GULF]);
    $body = Notification::query()->where('user_id', $this->payer->id)->where('template_key', 'billing.invoice_issued')->sole()->body;
    expect($body)->not->toContain('SA00')->and(preg_match('#https?://\S+/pay/invoice/\S+#', $body, $link))->toBe(1);
    $this->clearTenant();

    $this->get($link[0])->assertOk()->assertSee(__('billing::transfer.title'))->assertSee('IBAN: SA00 0000 0000 0000 0000 0000');
});

it('lets the academy write them on its payment settings screen, the academy\'s and a region\'s own', function () {
    $this->actingAs(User::factory()->withRole(Role::Owner)->create());
    Filament\Facades\Filament::setCurrentPanel('academy');

    $page = Livewire\Livewire::test(PaymentSettings::class)
        ->assertSee(__('billing::transfer.settings.academy'))
        ->assertSee(__('billing::transfer.settings.regions'))
        ->assertSee(__('billing::transfer.settings.region', ['name' => 'Egypt', 'currency' => 'EGP']))
        ->set('manualNote', TD_ACADEMY)
        ->set("regionNotes.{$this->gulf->id}", TD_GULF)
        ->call('saveManualNote')
        ->assertHasNoErrors()
        ->assertNotified(__('billing::transfer.settings.saved'));
    expect(BillingSettings::current()->manual_payment_note)->toBe(TD_ACADEMY)
        ->and($this->gulf->refresh()->transfer_details)->toBe(TD_GULF)
        ->and(DB::table('audit_logs')->where('action', 'billing.transfer_details_saved')->exists())->toBeTrue();

    $page->set("regionNotes.{$this->egypt->id}", 'InstaPay <a href="x">here</a>')->call('saveManualNote')
        ->assertHasErrors(["regionNotes.{$this->egypt->id}"]);
    $page->set('manualNote', str_repeat('x', 1001))->set("regionNotes.{$this->egypt->id}", null)->call('saveManualNote')
        ->assertHasErrors(['manualNote']);
    expect($this->egypt->refresh()->transfer_details)->toBeNull();

    // An admin has no business with the academy's payment accounts.
    $this->actingAs($this->admin);
    $this->clearTenant();
    $this->get('http://noor.lms.test/admin/settings/payments')->assertForbidden();
});
