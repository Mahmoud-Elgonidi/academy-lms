<?php

namespace Modules\Billing\Filament\Family;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Lessons left on each course of the learner on screen, and the invoices the
 * person signed in pays, each payable online on the academy's gateways
 * ("pay now": the gateway's page, then back here, which says how it went
 * and updates by itself once the gateway confirms), or paid by hand and
 * shown with the receipt ("I paid by transfer"), for the academy to confirm.
 */
final class FamilyBilling extends Page
{
    protected string $view = 'billing::filament.family.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'account';

    /** The checkout just back from a gateway, to say how it went. */
    #[Url]
    public ?string $checkout = null;

    public static function shouldRegisterNavigation(): bool
    {
        return FamilyAccount::shown();
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::family.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::family.title');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function account(): array
    {
        $viewer = (string) auth()->id();
        $learner = app(FamilyView::class)->current($viewer);

        return [
            'learner' => $learner?->name,
            'balances' => $learner ? FamilyAccount::balances($learner->id) : [],
            'invoices' => FamilyAccount::invoices($viewer, app(ViewerTimezone::class)->effective()),
            'owed' => FamilyAccount::owed($viewer),
            'channel' => PersonalChannel::name($viewer),
            'result' => $this->checkout ? PaymentCheckout::query()->where('payer_id', $viewer)->find($this->checkout) : null,
        ];
    }

    public function payAction(): Action
    {
        $l = 'billing::payments.family.';

        return Action::make('pay')
            ->label(__($l.'pay_now'))->icon(Heroicon::OutlinedCreditCard)->button()->size('sm')
            ->modalHeading(__($l.'choose'))->modalSubmitActionLabel(__($l.'go'))->modalWidth('md')
            ->modalHidden(fn (array $arguments) => count($this->accountsFor($arguments['invoice'] ?? '')) <= 1)
            ->schema(fn (array $arguments) => [
                Radio::make('account')->hiddenLabel()->required()
                    ->default($this->accountsFor($arguments['invoice'] ?? '')[0]->id ?? null)
                    ->options(collect($this->accountsFor($arguments['invoice'] ?? ''))->mapWithKeys(fn (GatewayAccount $a) => [$a->id => $a->label])->all())
                    ->descriptions(collect($this->accountsFor($arguments['invoice'] ?? ''))->mapWithKeys(fn (GatewayAccount $a) => [
                        $a->id => collect(GatewayManager::methods($a->gateway))->map(fn (string $m) => __('billing::payments.methods.'.$m))->implode(' · '),
                    ])->all()),
            ])
            ->action(function (array $data, array $arguments) {
                $accounts = collect($this->accountsFor($arguments['invoice'] ?? ''));
                $account = $accounts->firstWhere('id', $data['account'] ?? null) ?? $accounts->first();
                abort_if($account === null, 404);
                try {
                    $checkout = app(StartCheckout::class)->handle($this->ownInvoice($arguments['invoice']), $account, (string) auth()->id(), 'family');
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }
                $this->redirect((string) $checkout->url);
            });
    }

    public function sendProofAction(): Action
    {
        $l = 'billing::proofs.send.';

        return Action::make('sendProof')
            ->label(__($l.'action'))->icon(Heroicon::OutlinedDocumentArrowUp)->button()->size('sm')->outlined()
            ->modalHeading(__($l.'title'))->modalDescription(__($l.'hint'))->modalWidth('lg')
            ->modalSubmitActionLabel(__($l.'submit'))
            ->modalContent(fn () => view('billing::filament.family.how-to-pay', ['note' => app(OnlinePayments::class)->manualNote()]))
            ->fillForm(fn (array $arguments) => [
                'method' => 'bank_transfer',
                'amount' => $this->ownInvoice($arguments['invoice'] ?? '')->outstanding()->decimal(),
                'paid_on' => CarbonImmutable::now(app(ViewerTimezone::class)->effective())->toDateString(),
            ])
            ->schema(fn (array $arguments) => [
                ToggleButtons::make('method')->label(__($l.'method'))->inline()->required()
                    ->options(collect(PaymentProof::METHODS)->mapWithKeys(fn ($m) => [$m => __('billing::billing.methods.'.$m)])->all()),
                Grid::make(2)->schema([
                    TextInput::make('amount')->label(__($l.'amount'))->numeric()->minValue(0)->required()
                        ->suffix($this->ownInvoice($arguments['invoice'] ?? '')->currency),
                    DatePicker::make('paid_on')->label(__($l.'paid_on'))->native(false)->required()
                        ->maxDate(CarbonImmutable::now(app(ViewerTimezone::class)->effective())->toDateString()),
                ]),
                TextInput::make('reference')->label(__($l.'reference'))->placeholder(__($l.'reference_hint'))->maxLength(120),
                FileUpload::make('file')->label(__($l.'file'))->helperText(__($l.'file_hint'))->required()
                    ->storeFiles(false)->acceptedFileTypes(PaymentProof::MIMES)->maxSize(PaymentProof::MAX_KB),
                Textarea::make('note')->label(__($l.'note'))->placeholder(__($l.'note_hint'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data, array $arguments) use ($l) {
                $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : ($data['file'] ?? null);
                try {
                    app(SubmitPaymentProof::class)->handle($this->ownInvoice($arguments['invoice'] ?? ''), (string) auth()->id(), 'family',
                        $data['method'] ?? null, $data['amount'] ?? null, $data['paid_on'] ?? null, $data['reference'] ?? null, $data['note'] ?? null, $file);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

                    return;
                }
                Notification::make()->success()->title(__($l.'sent'))->send();
                unset($this->account);
            });
    }

    /** @var array<string, list<GatewayAccount>> per invoice, for this request */
    private array $accounts = [];

    /** @return list<GatewayAccount> */
    private function accountsFor(string $invoiceId): array
    {
        return $this->accounts[$invoiceId] ??= $invoiceId === '' ? [] : app(OnlinePayments::class)->accountsFor($this->ownInvoice($invoiceId));
    }

    /** An invoice the person signed in pays; anyone else's is not found. */
    private function ownInvoice(string $id): Invoice
    {
        $invoice = Invoice::query()->find($id);
        abort_unless($invoice !== null && DB::table('billing_accounts')->where('id', $invoice->billing_account_id)->value('payer_id') === auth()->id(), 404);

        return $invoice;
    }
}
