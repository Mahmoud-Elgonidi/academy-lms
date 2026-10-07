<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Actions\SaveBillingSettings;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Filament\Resources\Invoices\ViewInvoice;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Zatca\ZatcaQr;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Saudi e-invoicing, phase 1 (docs/architecture/03, "ZATCA"): a VAT-registered
 * academy issues simplified tax invoices with the seller's name, VAT number,
 * time of issue and the TLV QR code; an issued invoice never changes, and it
 * is cancelled only by a credit note.
 */

const VAT_NO = '310122393500003';

beforeEach(function () {
    $this->enrolStudentOnPlan();                                         // block 1: 400 SAR, issued before ZATCA was on
    $this->subscription = Invoice::query()->findOrFail($this->booked->invoice->id)->subscription;
});

afterEach(fn () => CarbonImmutable::setTestNow());

function zatcaOn(): void
{
    app(SaveBillingSettings::class)->handle(15, true, VAT_NO, 'NR-', 3, 'مؤسسة النور للتعليم', true);
}

it('writes the QR code as ZATCA\'s own example says', function () {
    $code = ZatcaQr::encode('Bobs Records', VAT_NO, CarbonImmutable::parse('2022-04-25 15:30:00', 'UTC'), Money::of(100000, 'SAR'), Money::of(15000, 'SAR'));

    expect($code)->toBe('AQxCb2JzIFJlY29yZHMCDzMxMDEyMjM5MzUwMDAwMwMUMjAyMi0wNC0yNVQxNTozMDowMFoEBzEwMDAuMDAFBjE1MC4wMA==')
        ->and(ZatcaQr::decode($code))->toBe([1 => 'Bobs Records', 2 => VAT_NO, 3 => '2022-04-25T15:30:00Z', 4 => '1000.00', 5 => '150.00']);
});

it('takes ZATCA invoicing only with a valid Saudi VAT number', function () {
    expect(fn () => app(SaveBillingSettings::class)->handle(15, true, '300000000000001', 'NR-', 3, null, true))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveBillingSettings::class)->handle(15, true, null, 'NR-', 3, null, true))->toThrow(ValidationException::class);

    zatcaOn();
    expect(ZatcaQr::validVatNumber('3101 2239 3500 003'))->toBeFalse();
});

it('issues a simplified tax invoice with the seller as registered and its QR code', function () {
    zatcaOn();
    CarbonImmutable::setTestNow('2026-10-20 09:15:30');
    $invoice = app(IssueBlockInvoice::class)->handle($this->subscription, 2, 'قرآن', 'Quran');

    expect($invoice->tax_document)->toBe('simplified')
        ->and($invoice->seller_name)->toBe('مؤسسة النور للتعليم')
        ->and($invoice->seller_tax_number)->toBe(VAT_NO)
        ->and($invoice->total_minor)->toBe(40000)->and($invoice->tax_minor)->toBe(5217)
        ->and(ZatcaQr::decode($invoice->zatca_qr))->toBe([1 => 'مؤسسة النور للتعليم', 2 => VAT_NO, 3 => '2026-10-20T09:15:30Z', 4 => '400.00', 5 => '52.17']);

    // Later settings never rewrite it, and nothing may.
    app(SaveBillingSettings::class)->handle(15, true, VAT_NO, 'NR-', 3, 'اسم جديد', true);
    expect($invoice->fresh()->seller_name)->toBe('مؤسسة النور للتعليم');
    $invoice->seller_name = 'غيره';
    expect(fn () => $invoice->save())->toThrow(LogicException::class);
});

it('draws no QR on an invoice in another currency (its VAT in riyals is not done yet), and none without ZATCA', function () {
    $before = Invoice::query()->findOrFail($this->booked->invoice->id);
    expect($before->tax_document)->toBeNull()->and($before->zatca_qr)->toBeNull()->and($before->seller_name)->not->toBeNull();

    zatcaOn();
    $this->subscription->forceFill(['currency' => 'USD'])->saveQuietly();
    $usd = app(IssueBlockInvoice::class)->handle($this->subscription->fresh(), 2, 'قرآن', 'Quran');
    expect($usd->tax_document)->toBe('simplified')->and($usd->zatca_qr)->toBeNull();
});

it('cancels a tax invoice with a credit note of its own number and QR, which never changes', function () {
    zatcaOn();
    $invoice = app(IssueBlockInvoice::class)->handle($this->subscription, 2, 'قرآن', 'Quran');
    CarbonImmutable::setTestNow('2026-10-21 08:00:00');
    $void = app(VoidInvoice::class)->handle($invoice->id, 'اتعملت بالغلط', $this->admin->id);

    $note = CreditNote::query()->sole();
    expect($void->status)->toBe('void')->and($void->credit_note_id)->toBe($note->id)
        ->and($note->number)->toBe('CN-000001')->and($note->reason)->toBe('اتعملت بالغلط')
        ->and($note->total_minor)->toBe(40000)->and($note->tax_minor)->toBe(5217)
        ->and(ZatcaQr::decode($note->zatca_qr)[3])->toBe('2026-10-21T08:00:00Z');

    expect(fn () => $note->update(['reason' => 'غيره']))->toThrow(QueryException::class, 'never change');

    // An invoice issued before ZATCA is voided as before, with no credit note.
    app(VoidInvoice::class)->handle($this->booked->invoice->id, 'اتعملت بالغلط', $this->admin->id);
    expect(CreditNote::query()->count())->toBe(1);
});

it('shows the title, time and QR code to the back office and to the payer, with the credit note', function () {
    zatcaOn();
    $invoice = app(IssueBlockInvoice::class)->handle($this->subscription, 2, 'قرآن', 'Quran');
    app()->setLocale('ar');
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSee('فاتورة ضريبية مبسطة')->assertSee('Simplified Tax Invoice')->assertSee(VAT_NO)
        ->assertSeeHtml('class="qr-svg zatca-qr"');

    app(VoidInvoice::class)->handle($invoice->id, 'اتعملت بالغلط', $this->admin->id);
    $url = app(PayLinks::class)->document($invoice->fresh(), null);
    $this->get($url, ['Accept-Language' => 'ar'])->assertOk()
        ->assertSee('فاتورة ضريبية مبسطة')->assertSee('إشعار دائن')->assertSee('CN-000001')
        ->assertSee('مؤسسة النور للتعليم')->assertSeeHtml('zatca-qr');
});
