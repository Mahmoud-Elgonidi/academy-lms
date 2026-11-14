<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Actions\Metering\SaveUsageAlerts;
use Modules\Platform\Actions\Recharging\RemovePlatformCard;
use Modules\Platform\Actions\Recharging\SaveAutoRecharge;
use Modules\Platform\Actions\Recharging\SettleTopUpPayment;
use Modules\Platform\Actions\Recharging\StartTopUpCheckout;
use Modules\Platform\Domain\Metering\Burn;
use Modules\Platform\Domain\Recharging\AutoRechargeRules;
use Modules\Platform\Live\RechargeChanged;
use Modules\Platform\Metering\AiCreditRates;
use Modules\Platform\Metering\ExportLedger;
use Modules\Platform\Metering\MeterFormat;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Metering\UsageAlerts;
use Modules\Platform\Metering\UsageReport;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\Recharging\RechargeOffer;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Throwable;
use UnitEnum;

/**
 * Settings › Usage and balance (docs/architecture/02, section 11), for whoever
 * sees the plan: for each meter (video minutes, AI credits) this month's
 * allowance used and left, the bought balance with its lots and their ends,
 * any overage owed and when the balance runs out at the recent pace, with the
 * ways on (an extra pack, a bigger plan: the plan page's own requests); the
 * daily use this month beside last month's, AI credits by helper and by
 * person, video minutes by teacher; and the ledger as a statement with its
 * spreadsheet. Everything is read from the usage ledger each time.
 *
 * Recharging (the platform is the merchant): a pack priced in the academy's
 * currency is bought here on Stripe's page (StartTopUpCheckout), optionally
 * keeping the card; with it, automatic recharge per meter (SaveAutoRecharge)
 * and the card's removal (RemovePlatformCard); the purchases with their
 * receipts. Back from Stripe (?purchase=) the page says calmly where the
 * payment stands and follows it live (recharge:{academy}) until it is added.
 */
final class UsagePage extends Page
{
    protected string $view = 'platform::plans.usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 914;

    protected static ?string $slug = 'settings/usage';

    /** The statement's meter. */
    #[Url(except: 'video_minutes')]
    public string $meter = 'video_minutes';

    public int $lines = 25;

    /** Back from Stripe's page: the purchase to say where it stands. */
    #[Url(except: '')]
    public string $purchase = '';

    #[Url(except: '')]
    public string $cancelled = '';

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->check() && Gate::allows(Ability::ViewPlan->value)
            && SaasSubscription::currentFor(app(TenantContext::class)->require()->id) !== null;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavGroup::Settings;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::metering.page.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::metering.page.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::metering.page.subtitle');
    }

    public function mount(): void
    {
        if (! in_array($this->meter, array_map(fn (Meter $m) => $m->value, $this->shownMeters()), true)) {
            $this->meter = Meter::VideoMinutes->value;
        }
        // Back from Stripe: ask it once now, so a payment that went through is added without waiting for its webhook.
        $back = $this->returnedPurchase();
        if ($back !== null && $back->isOpen() && $back->method === TopUpPurchase::CARD && $this->cancelled === '') {
            try {
                app(SettleTopUpPayment::class)->handle($back);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** The academy's purchases follow Stripe's answers live. */
    public function liveChannel(): string
    {
        return RechargeChanged::channel($this->academyId());
    }

    public function buyOnlineAction(): Action
    {
        $l = 'platform::recharge.buy.';

        return Action::make('buyOnline')->label(__($l.'label'))->icon(Heroicon::OutlinedCreditCard)->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'heading', ['meter' => self::meterOf($arguments)->label()]))
            ->modalDescription(__($l.'description'))
            ->modalSubmitActionLabel(__($l.'submit'))
            ->schema(fn (array $arguments) => [
                Radio::make('pack')->label(__($l.'pack'))->required()->options(RechargeOffer::packOptions(self::meterOf($arguments), $this->currency())),
                Checkbox::make('save_card')->label(__($l.'save_card'))->helperText(__($l.'save_card_hint')),
            ])
            ->action(function (array $data): void {
                $started = app(StartTopUpCheckout::class)->handle((string) $data['pack'], (bool) ($data['save_card'] ?? false), (string) auth()->id());
                $this->redirect($started['url']);
            });
    }

    public function autoRechargeAction(): Action
    {
        $l = 'platform::recharge.auto.';

        return Action::make('autoRecharge')->label(__($l.'edit'))->icon(Heroicon::OutlinedArrowPath)->size('sm')->color('gray')
            ->modalHeading(fn (array $arguments) => __($l.'heading', ['meter' => self::meterOf($arguments)->label()]))
            ->modalDescription(__($l.'description'))
            ->modalSubmitActionLabel(__($l.'save'))
            ->fillForm(function (array $arguments): array {
                $setting = AutoRecharge::for($this->academyId(), self::meterOf($arguments));

                return $setting === null ? ['enabled' => true, 'monthly_cap' => 3, 'threshold' => self::meterOf($arguments) === Meter::VideoMinutes ? 100 : 50]
                    : ['enabled' => $setting->enabled, 'pack_id' => $setting->pack_id, 'threshold' => $setting->threshold, 'monthly_cap' => $setting->monthly_cap];
            })
            ->schema(fn (array $arguments) => [
                Toggle::make('enabled')->label(__($l.'enabled')),
                Select::make('pack_id')->label(__($l.'pack'))->required()->native(false)->options(RechargeOffer::packOptions(self::meterOf($arguments), $this->currency())),
                TextInput::make('threshold')->label(__($l.'threshold'))->helperText(__($l.'threshold_hint'))->required()->numeric()->integer()->minValue(0)
                    ->suffix(__('platform::metering.units_name.'.self::meterOf($arguments)->value)),
                TextInput::make('monthly_cap')->label(__($l.'cap'))->helperText(__($l.'cap_hint'))->required()->numeric()->integer()->minValue(1)->maxValue(AutoRechargeRules::CAP_MAX),
            ])
            ->action(function (array $data, array $arguments): void {
                app(SaveAutoRecharge::class)->handle(self::meterOf($arguments), [
                    'enabled' => (bool) ($data['enabled'] ?? false), 'pack_id' => (string) ($data['pack_id'] ?? ''),
                    'threshold' => (int) ($data['threshold'] ?? 0), 'monthly_cap' => (int) ($data['monthly_cap'] ?? 1),
                ], (string) auth()->id());
                Notification::make()->success()->title(__('platform::recharge.auto.saved'))->send();
                unset($this->recharge);
            });
    }

    public function removeCardAction(): Action
    {
        $l = 'platform::recharge.card.';

        return Action::make('removeCard')->label(__($l.'remove'))->icon(Heroicon::OutlinedTrash)->color('danger')->size('sm')->link()
            ->requiresConfirmation()->modalHeading(__($l.'remove_heading'))->modalDescription(__($l.'remove_body'))
            ->action(function () use ($l): void {
                app(RemovePlatformCard::class)->handle();
                Notification::make()->success()->title(__($l.'removed'))->send();
                unset($this->recharge);
            });
    }

    /** @return array<string, mixed> buying online, the saved card, automatic recharge, the purchases and the way back */
    #[Computed]
    public function recharge(): array
    {
        return app(RechargeOffer::class)->forAcademy($this->academyId(), $this->currency(), $this->shownMeters(), $this->returnedPurchase(), $this->cancelled !== '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('plan')->label(__('platform::metering.page.plan'))->icon(Heroicon::OutlinedRectangleStack)->color('gray')
                ->url(AcademyPlanPage::getUrl()),
            Action::make('export')->label(__('platform::metering.page.export'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(function () {
                    $export = app(ExportLedger::class);

                    return response()->download($export->handle($this->academyId(), $this->shownMeters()), $export->filename())->deleteFileAfterSend();
                }),
        ];
    }

    /** Whether the staff hear at half of the month's allowance too. */
    public function halfAlertAction(): Action
    {
        $on = AcademyMetering::for($this->academyId())->half_alert;

        return Action::make('halfAlert')->size('sm')->color('gray')
            ->label($on ? __('platform::metering.alerts.turn_off') : __('platform::metering.alerts.turn_on'))
            ->icon($on ? Heroicon::OutlinedBellSlash : Heroicon::OutlinedBellAlert)
            ->action(function () use ($on): void {
                app(SaveUsageAlerts::class)->handle(! $on, (string) auth()->id());
                Notification::make()->success()->title(__('platform::metering.alerts.saved'))->send();
            });
    }

    /** @return list<array<string, mixed>> one card for each meter */
    #[Computed]
    public function cards(): array
    {
        $academy = app(TenantContext::class)->require();
        $meters = app(Meters::class);
        $alerts = app(UsageAlerts::class);
        $waiting = UsageTopUp::query()->where('academy_id', $academy->id)->where('status', 'requested')->pluck('limit')->all();
        $tz = $academy->defaultTimezone;
        $renews = $meters->month($academy->id)->to->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F');
        $hard = AcademyMetering::for($academy->id)->video_hard_limit;
        $out = [];
        foreach ($this->shownMeters() as $meter) {
            $state = $meters->state($academy->id, $meter);
            $running = $meter === Meter::VideoMinutes ? app(UsageMeters::class)->runningNow(Limit::VideoMinutes) : 0;
            $available = $state->available() - $running;
            $days = Burn::daysLeft(max(0, $available), $alerts->daily($academy->id, $meter));
            $percent = $state->allowance > 0 ? (int) min(100, round(100 * $state->allowanceUsed() / $state->allowance)) : null;
            $f = fn (int $n) => MeterFormat::amount($meter, $n);
            $out[] = [
                'key' => $meter->value,
                'limit' => $meter->limit()->value,
                'label' => $meter->label(),
                'limited' => $state->limited,
                'used' => $f($state->used + $running),
                'running' => $running > 0 ? __('platform::metering.card.running', ['n' => $f($running)]) : null,
                'allowance' => $state->allowance > 0 ? __('platform::metering.card.allowance', ['used' => $f($state->allowanceUsed()), 'max' => $f($state->allowance)]) : null,
                'allowance_left' => $f($state->allowanceLeft),
                'percent' => $percent,
                'state' => match (true) {
                    ! $state->limited => 'ok',
                    $available <= 0 => 'full',
                    $percent !== null && $percent >= 80 => 'near',
                    default => 'ok',
                },
                'renews' => __('platform::metering.card.renews', ['date' => $renews]),
                'bought' => $f($state->bought),
                'has_bought' => $state->bought > 0,
                'lots' => array_map(fn (array $lot) => [
                    'id' => $lot['id'],
                    'what' => __('platform::metering.kinds.'.$lot['kind']),
                    'left' => __('platform::metering.card.lot_left', ['left' => $f($lot['left']), 'of' => $f($lot['amount'])]),
                    'ends' => __('platform::metering.card.lot_ends', ['date' => $lot['expires_at']->subSecond()->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y')]),
                    'percent' => $lot['amount'] > 0 ? (int) round(100 * $lot['left'] / $lot['amount']) : 0,
                ], $state->lots),
                'debt' => $state->debt > 0 ? __('platform::metering.card.debt', ['n' => $f($state->debt)]) : null,
                'available' => $f(max(0, $available)),
                'runout' => ! $state->limited ? __('platform::metering.card.unlimited')
                    : ($available <= 0 ? __('platform::metering.card.none_left.'.$meter->value.($meter === Meter::VideoMinutes && $hard ? '_hard' : ''))
                        : ($days === null ? __('platform::metering.card.no_pace')
                            : __('platform::metering.card.runout', ['date' => CarbonImmutable::now()->addDays($days)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F')]))),
                'hard' => $meter === Meter::VideoMinutes && $hard,
                'waiting' => in_array($meter->limit()->value, $waiting, true),
                'buy_url' => AcademyPlanPage::getUrl(['buy' => $meter->limit()->value]),
                'buy_online' => StripeClient::configured() && RechargeOffer::packOptions($meter, $this->currency()) !== [],
                'upgrade_url' => AcademyPlanPage::getUrl(['upgrade' => 1]),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> the charts of each meter, this month */
    #[Computed]
    public function charts(): array
    {
        $academyId = $this->academyId();
        $report = app(UsageReport::class);
        $meters = app(Meters::class);
        $month = $meters->month($academyId);
        $last = UsagePeriod::monthOf($month->from->subDay(), $meters->zone($academyId));
        $out = [];
        foreach ($this->shownMeters() as $meter) {
            $now = $report->daily($academyId, $meter, $month);
            $before = $report->daily($academyId, $meter, $last);
            $top = max(1, max($now ?: [0]), max($before ?: [0]));
            $days = [];
            foreach (range(1, max(count($now), count($before))) as $d) {
                $days[] = ['day' => $d, 'now' => $now[$d] ?? null, 'before' => $before[$d] ?? null,
                    'now_pct' => isset($now[$d]) ? round(100 * $now[$d] / $top, 1) : 0, 'before_pct' => isset($before[$d]) ? round(100 * $before[$d] / $top, 1) : 0];
            }
            $subjects = [];
            foreach ($report->bySubject($academyId, $meter, $month) as $subject => $used) {
                $subjects[] = ['name' => MeterFormat::subject($subject), 'used' => $used];
            }
            $people = array_map(fn ($p) => ['name' => $p['name'] ?? __('platform::metering.subjects.unknown_person'), 'used' => $p['used']], $report->byPerson($academyId, $meter, $month));
            $out[$meter->value] = [
                'label' => $meter->label(),
                'days' => $days,
                'this_total' => MeterFormat::amount($meter, array_sum($now)),
                'last_total' => MeterFormat::amount($meter, array_sum($before)),
                'by_subject' => $meter === Meter::AiCredits ? self::bars($subjects, $meter) : [],
                'by_person' => self::bars($people, $meter),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, used: int}>  $rows
     * @return list<array{name: string, used: int, text: string, pct: float}>
     */
    private static function bars(array $rows, Meter $meter): array
    {
        $max = max([1, ...array_column($rows, 'used')]);

        return array_map(fn (array $r) => $r + ['text' => MeterFormat::amount($meter, $r['used']), 'pct' => round(100 * $r['used'] / $max, 1)], $rows);
    }

    /** @return array{lines: list<array<string, mixed>>, more: bool, unit: string} */
    #[Computed]
    public function statement(): array
    {
        $academyId = $this->academyId();
        $meter = Meter::tryFrom($this->meter) ?? Meter::VideoMinutes;
        $report = app(UsageReport::class);
        $tz = app(Meters::class)->zone($academyId);
        $lines = array_map(fn (array $line) => [
            'when' => $line['at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M Y · H:i')),
            'what' => MeterFormat::line($line),
            'reason' => $line['reason'],
            'amount' => MeterFormat::signed($meter, $line['amount']),
            'sign' => $line['amount'] > 0 ? 'in' : 'out',
            'after' => number_format($line['after']),
            'ends' => $line['expires_at'] === null ? null : __('platform::metering.card.lot_ends', ['date' => $line['expires_at']->subSecond()->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y')]),
        ], $report->statement($academyId, $meter, $this->lines));

        return ['lines' => $lines, 'more' => $report->statementCount($academyId, $meter) > $this->lines, 'unit' => __('platform::metering.units_name.'.$meter->value)];
    }

    /** @return list<array{key: string, label: string, base: int, per_minute: int}> what each helper costs, for the AI card */
    #[Computed]
    public function rates(): array
    {
        if (! in_array(Meter::AiCredits, $this->shownMeters(), true)) {
            return [];
        }

        return array_values(array_map(fn (array $r) => ['key' => $r['capability']->key, 'label' => $r['capability']->label(), 'base' => $r['base'], 'per_minute' => $r['per_minute']], app(AiCreditRates::class)->all()));
    }

    #[Computed]
    public function halfAlert(): bool
    {
        return AcademyMetering::for($this->academyId())->half_alert;
    }

    public function showMeter(string $meter): void
    {
        if (Meter::tryFrom($meter) !== null) {
            $this->meter = $meter;
            $this->lines = 25;
            unset($this->statement);
        }
    }

    public function more(): void
    {
        $this->lines = min(500, $this->lines + 50);
        unset($this->statement);
    }

    /** @return list<Meter> video, and the AI credits while the academy has the AI area */
    private function shownMeters(): array
    {
        return array_values(array_filter(Meter::cases(), fn (Meter $m) => $m !== Meter::AiCredits || app(PlatformAcademyPlan::class)->has(Feature::Ai)));
    }

    private function academyId(): string
    {
        return app(TenantContext::class)->require()->id;
    }

    /** The academy's currency: packs are bought online in it. */
    private function currency(): string
    {
        return (string) (Academy::query()->whereKey($this->academyId())->value('base_currency') ?? 'USD');
    }

    private function returnedPurchase(): ?TopUpPurchase
    {
        return Str::isUuid($this->purchase) ? TopUpPurchase::query()->where('academy_id', $this->academyId())->find($this->purchase) : null;
    }

    /** @param  array<string, mixed>  $arguments */
    private static function meterOf(array $arguments): Meter
    {
        return Meter::tryFrom((string) ($arguments['meter'] ?? '')) ?? Meter::VideoMinutes;
    }
}
