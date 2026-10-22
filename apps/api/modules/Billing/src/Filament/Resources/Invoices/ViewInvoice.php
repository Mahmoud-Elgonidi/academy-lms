<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Filament\InvoiceActions;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\InvoiceDocument;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One invoice as the payer receives it, printable, with what has been paid
 * and the actions that apply: its pay link (to send by hand), and each go
 * at paying it online. A Saudi tax invoice shows its QR code and, once
 * cancelled, its credit note. A proof of paying by hand waits on top, with
 * its receipt, to approve (recording the payment) or turn down.
 *
 * @property Invoice $record
 */
final class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected string $view = 'billing::filament.invoice';

    public function getTitle(): string|Htmlable
    {
        return __('billing::invoices.model').' '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActions::recordPayment()->record($this->record)->after(fn () => $this->record->refresh())
                ->hidden(fn () => $this->waitingProof() !== null),
            InvoiceActions::void()->record($this->record)->after(fn () => $this->record->refresh()),
            Action::make('payLink')->label(__('billing::payments.invoice.copy_link'))->icon(Heroicon::OutlinedLink)->color('gray')
                ->visible(fn () => $this->record->status === 'issued')
                ->modalHeading(__('billing::payments.invoice.copy_link'))->modalDescription(__('billing::payments.invoice.copy_link_hint'))
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('billing::filament.pay-link', ['url' => app(PayLinks::class)->invoice($this->record, $this->record->billingAccount?->payer_id)])),
            Action::make('print')->label(__('billing::invoices.print'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->alpineClickHandler('window.print()'),
        ];
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        $invoice = $this->record;
        $tz = app(ViewerTimezone::class)->effective();
        $d = app(InvoiceDocument::class)->build($invoice, $tz);
        $date = fn ($at) => $at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y');

        return $d + [
            'dueLine' => InvoiceResource::dueLine($invoice),
            'checkouts' => PaymentCheckout::query()->with('account')->where('invoice_id', $invoice->id)->latest()->get()->map(fn (PaymentCheckout $c) => [
                'when' => $date($c->created_at).' · '.$c->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i')),
                'gateway' => $c->account?->label,
                'status' => $c->status,
                'amount' => $c->money()->format(),
                'ref' => $c->provider_payment_ref ?? $c->provider_ref,
                'failure' => $c->failure,
            ])->all(),
            'enrollmentUrl' => $d['student']?->enrollment_id ? route('filament.academy.resources.enrollments.view', ['record' => $d['student']->enrollment_id]) : null,
        ];
    }

    /** @return list<array<string, mixed>> the proofs sent for this invoice, the latest first */
    public function proofs(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $proofs = PaymentProof::query()->where('invoice_id', $this->record->id)->latest()->get();
        $names = DB::table('users')->whereIn('id', $proofs->pluck('payer_id')->merge($proofs->pluck('decided_by'))->filter()->unique())->pluck('name', 'id');
        $when = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j F Y · H:i'));

        return $proofs->map(fn (PaymentProof $p) => [
            'id' => $p->id,
            'status' => $p->status,
            'amount' => $p->money()->format(),
            'method' => __('billing::billing.methods.'.$p->method),
            'paidOn' => $p->paid_on->locale(app()->getLocale())->translatedFormat('l j F Y'),
            'reference' => $p->reference,
            'note' => $p->note,
            'sentBy' => $names[$p->payer_id] ?? null,
            'sentAt' => $when($p->created_at),
            'viaLink' => $p->channel === 'link',
            'decidedBy' => $p->decided_by ? ($names[$p->decided_by] ?? null) : null,
            'decidedAt' => $when($p->decided_at),
            'reason' => $p->decision_note,
            'file' => route('billing.proofs.file', ['proof' => $p->id]),
            'image' => $p->isImage(),
            'fileName' => $p->file_name,
        ])->all();
    }

    public function approveProofAction(): Action
    {
        $l = 'billing::proofs.review.';

        return Action::make('approveProof')
            ->label(__($l.'approve'))->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->visible(fn () => $this->waitingProof() !== null && $this->record->status === 'issued' && Gate::allows(Ability::RecordPayments->value))
            ->modalHeading(__($l.'approve_title', ['number' => $this->record->number]))
            ->modalDescription(__($l.'approve_hint'))
            ->modalSubmitActionLabel(__($l.'approve'))
            ->fillForm(function () {
                $proof = $this->waitingProof();
                $tz = app(ViewerTimezone::class)->effective();
                $arrived = CarbonImmutable::parse($proof->paid_on->toDateString().' 12:00', $tz)->utc();

                return [
                    'amount' => $proof->money()->decimal(),
                    'method' => $proof->method,
                    'received_at' => $arrived->min(CarbonImmutable::now()->utc())->format('Y-m-d H:i:s'),
                    'reference' => $proof->reference,
                ];
            })
            ->schema(fn () => [
                ToggleButtons::make('method')->label(__($l.'method'))->inline()->required()
                    ->options(collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => __('billing::billing.methods.'.$m)])->all()),
                Grid::make(2)->schema([
                    TextInput::make('amount')->label(__($l.'received_amount'))->numeric()->minValue(0)->required()->suffix($this->record->currency),
                    DateTimePicker::make('received_at')->label(__($l.'received_at'))->seconds(false)->native(false)->displayFormat(Clock::pattern('M j, Y H:i'))->required()
                        ->timezone(app(ViewerTimezone::class)->effective()),
                ]),
                TextInput::make('reference')->label(__($l.'reference_field'))->maxLength(120),
                Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data) use ($l) {
                $proof = $this->waitingProof() ?? abort(404);
                $invoice = app(ApprovePaymentProof::class)->handle($proof->id, Money::parse($data['amount'], $this->record->currency), $data['method'],
                    $data['reference'] ?? null, CarbonImmutable::parse($data['received_at'], 'UTC'), $data['note'] ?? null, (string) auth()->id());
                Notification::make()->success()->title(__($l.'approved'))
                    ->body($invoice->status === 'paid' ? __('billing::invoices.pay.paid', ['number' => $invoice->number]) : __('billing::invoices.pay.partial', ['left' => $invoice->outstanding()->format()]))
                    ->send();
                $this->record->refresh();
            });
    }

    public function rejectProofAction(): Action
    {
        $l = 'billing::proofs.review.';

        return Action::make('rejectProof')
            ->label(__($l.'reject'))->icon(Heroicon::OutlinedXCircle)->color('danger')->outlined()
            ->visible(fn () => $this->waitingProof() !== null && Gate::allows(Ability::RecordPayments->value))
            ->modalHeading(__($l.'reject_title', ['number' => $this->record->number]))
            ->modalDescription(__($l.'reject_hint'))
            ->modalSubmitActionLabel(__($l.'reject'))
            ->schema([Textarea::make('reason')->label(__($l.'reason_field'))->placeholder(__($l.'reason_hint'))->required()->maxLength(300)->rows(2)])
            ->action(function (array $data) use ($l) {
                $proof = $this->waitingProof() ?? abort(404);
                app(RejectPaymentProof::class)->handle($proof->id, $data['reason'], (string) auth()->id());
                Notification::make()->success()->title(__($l.'rejected'))->send();
            });
    }

    private function waitingProof(): ?PaymentProof
    {
        return PaymentProof::pendingFor($this->record->id);
    }
}
