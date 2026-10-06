<?php

namespace Modules\Billing\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PayLinks;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * The academy's gateway accounts (it is the merchant: money goes straight to
 * its own accounts), the webhook address each one needs, a check that the
 * keys work, how to pay by hand, and the latest payments made online.
 */
final class PaymentSettings extends Page
{
    protected string $view = 'billing::filament.payment-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 86;

    protected static ?string $slug = 'settings/payments';

    /** How to pay by hand, shown with every invoice. */
    public ?string $manualNote = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageBilling->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::payments.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::payments.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing::payments.settings.subtitle');
    }

    public function mount(): void
    {
        $this->manualNote = BillingSettings::current()->manual_payment_note;
    }

    /** @return list<array{account: GatewayAccount, webhook: string}> */
    #[Computed]
    public function accounts(): array
    {
        $links = app(PayLinks::class);

        return GatewayAccount::query()->orderBy('sort')->orderBy('created_at')->get()
            ->map(fn (GatewayAccount $a) => ['account' => $a, 'webhook' => $links->webhook($a)])->all();
    }

    /** @return list<object> the latest online payments, with their invoice and payer */
    #[Computed]
    public function checkouts(): array
    {
        return PaymentCheckout::query()
            ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'payment_checkouts.academy_id')->on('i.id', '=', 'payment_checkouts.invoice_id'))
            ->join('gateway_accounts as g', fn ($j) => $j->on('g.academy_id', '=', 'payment_checkouts.academy_id')->on('g.id', '=', 'payment_checkouts.gateway_account_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'payment_checkouts.academy_id')->on('u.id', '=', 'payment_checkouts.payer_id'))
            ->latest('payment_checkouts.created_at')->limit(15)
            ->get(['payment_checkouts.*', 'i.number', 'g.label', 'u.name as payer'])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label(__('billing::payments.settings.add'))->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('billing::payments.settings.add'))->modalWidth('lg')->modalSubmitActionLabel(__('billing::payments.settings.save'))
                ->schema(fn () => $this->accountSchema(null))
                ->action(function (array $data) {
                    app(SaveGatewayAccount::class)->handle(null, $data, (string) auth()->id());
                    unset($this->accounts);
                    Notification::make()->success()->title(__('billing::payments.settings.saved'))->send();
                }),
        ];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('billing::payments.settings.edit'))->icon(Heroicon::OutlinedPencilSquare)->link()->size('sm')
            ->modalHeading(fn (array $arguments) => $this->account($arguments)->label)->modalWidth('lg')->modalSubmitActionLabel(__('billing::payments.settings.save'))
            ->fillForm(fn (array $arguments) => collect($this->account($arguments)->only(['label', 'mode', 'currencies', 'is_active']))->all())
            ->schema(fn (array $arguments) => $this->accountSchema($this->account($arguments)))
            ->action(function (array $data, array $arguments) {
                app(SaveGatewayAccount::class)->handle($this->account($arguments), $data, (string) auth()->id());
                unset($this->accounts);
                Notification::make()->success()->title(__('billing::payments.settings.saved'))->send();
            });
    }

    public function testAction(): Action
    {
        return Action::make('test')->label(__('billing::payments.settings.test'))->icon(Heroicon::OutlinedSignal)->link()->size('sm')->color('gray')
            ->action(function (array $arguments) {
                $error = rescue(fn () => app(GatewayManager::class)->for($this->account($arguments))->check(), fn ($e) => $e->getMessage(), report: false);
                $error === null
                    ? Notification::make()->success()->title(__('billing::payments.settings.test_ok'))->send()
                    : Notification::make()->danger()->title(__('billing::payments.settings.test_failed'))->body($error)->send();
            });
    }

    public function saveManualNote(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->validate(['manualNote' => ['nullable', 'string', 'max:1000']]);
        $settings = BillingSettings::current();
        $settings->manual_payment_note = filled($this->manualNote) ? trim($this->manualNote) : null;
        $settings->save();
        Notification::make()->success()->title(__('billing::payments.settings.saved'))->send();
    }

    private function account(array $arguments): GatewayAccount
    {
        return GatewayAccount::query()->findOrFail($arguments['account'] ?? null);
    }

    /** @return list<Component|Field> */
    private function accountSchema(?GatewayAccount $account): array
    {
        $p = 'billing::payments.';
        $gateway = fn (Get $get) => $account?->gateway ?? $get('gateway');
        $needs = fn (string $field) => fn (Get $get) => array_key_exists($field, GatewayManager::fields((string) $gateway($get)));

        return [
            Select::make('gateway')->label(__($p.'fields.gateway'))->required()->live()->visible($account === null)
                ->options(collect(app(GatewayManager::class)->gateways())->mapWithKeys(fn (string $g) => [$g => __($p.'gateways.'.$g)])->all()),
            TextInput::make('label')->label(__($p.'fields.label'))->required()->maxLength(80)->helperText(__($p.'hints.label')),
            Radio::make('mode')->label(__($p.'fields.mode'))->inline()->default('test')
                ->options(['test' => __($p.'modes.test'), 'live' => __($p.'modes.live')])
                ->visible(fn (Get $get) => $gateway($get) !== null && $gateway($get) !== 'demo'),
            Select::make('currencies')->label(__($p.'fields.currencies'))->multiple()->helperText(__($p.'hints.currencies'))
                ->options(fn (Get $get) => collect(GatewayManager::currencies((string) $gateway($get)) ?? $this->knownCurrencies())->mapWithKeys(fn (string $c) => [$c => $c])->all()),
            TextInput::make('secret_key')->label(__($p.'fields.secret_key'))->password()->revealable()->autocomplete('off')
                ->required(fn (Get $get) => $account === null && $needs('secret_key')($get))->visible($needs('secret_key'))
                ->helperText($account ? __($p.'hints.keep') : null),
            TextInput::make('webhook_secret')->label(__($p.'fields.webhook_secret'))->password()->revealable()->autocomplete('off')
                ->required(fn (Get $get) => $account === null && $needs('webhook_secret')($get))->visible($needs('webhook_secret'))
                ->helperText(fn (Get $get) => trim(($account ? __($p.'hints.keep').' ' : '').__($p.'hints.webhook_secret.'.$gateway($get)))),
            Toggle::make('is_active')->label(__($p.'fields.is_active'))->default(true),
        ];
    }

    /** @return list<string> the academy's own currencies first, then the usual ones */
    private function knownCurrencies(): array
    {
        return collect(DB::table('price_regions')->pluck('currency'))
            ->merge(['SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR', 'EGP', 'JOD', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'])
            ->unique()->values()->all();
    }
}
