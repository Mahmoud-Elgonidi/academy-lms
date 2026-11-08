<?php

namespace Modules\Platform\Filament\Console\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Metering\GrantUsage;
use Modules\Platform\Actions\Metering\SetVideoHardLimit;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\SetAcademyStatus;
use Modules\Platform\Console\AcademyPeople;
use Modules\Platform\Console\AcademyUsage;
use Modules\Platform\Console\AuditFeed;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Metering\MeterFormat;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Metering\UsageReport;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\MeasureUsage;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\PlanSummary;
use Modules\Platform\Plans\PlanTerms;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * One academy for the platform team: its use, who runs it, its latest
 * sensitive actions, and switching it off or back on. Its people's data is
 * reached only by signing in as one of them (Identity's support sign-in).
 */
final class AcademyOverview extends Page
{
    protected string $view = 'platform::console.overview';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'academies/{academy}';

    public string $academy;

    public function mount(string $academy): void
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $academy) && Academy::query()->whereKey($academy)->exists(), 404);
        $this->academy = $academy;
    }

    #[Computed]
    public function record(): Academy
    {
        return Academy::query()->with('domains')->findOrFail($this->academy);
    }

    public function getTitle(): string|Htmlable
    {
        return ConsoleView::name($this->record);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::console.overview.subtitle', [
            'host' => $this->host(),
            'date' => ConsoleView::date($this->record->created_at, app(ViewerTimezone::class)->effective()),
        ]).' '.ZoneHint::yours();
    }

    public function getBreadcrumbs(): array
    {
        return [Academies::getUrl() => __('platform::console.overview.back')];
    }

    protected function getHeaderActions(): array
    {
        $record = $this->record;
        $name = ConsoleView::name($record);
        $active = $record->status === AcademyStatus::Active;

        $actions = [];
        // Signing in as someone is Identity's (its console plugin adds the page).
        if ($active && Route::has('filament.console.pages.support-sign-in')) {
            $actions[] = Action::make('support')->label(__('platform::console.overview.support'))->icon(Heroicon::OutlinedLifebuoy)
                ->url(route('filament.console.pages.support-sign-in', ['academy' => $record->id]));
        }
        // The academy's own AI routes (owners: console › AI routing, for this academy).
        if (AiRoutesPage::canAccess()) {
            $actions[] = Action::make('aiRoutes')->label(__('platform::routes.from_academy'))->icon(Heroicon::OutlinedArrowsRightLeft)->color('gray')
                ->url(AiRoutesPage::getUrl(['academy' => $record->id]));
        }
        if ($active) {
            $actions[] = Action::make('open')->label(__('platform::console.overview.open_site'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(app(TenantDirectory::class)->baseUrl($record->toTenantRef()).'/admin', shouldOpenInNewTab: true);
        }
        $actions[] = $this->planAction();
        // The academy's balance (owners): a bonus, a correction or a refund, and the hard limit on video.
        if (AiCreditRatesPage::canAccess() && SaasSubscription::currentFor($this->academy) !== null) {
            $actions[] = $this->creditAction();
            $actions[] = $this->hardLimitAction();
        }
        $actions[] = $active
            ? Action::make('suspend')->label(__('platform::console.overview.suspend.label'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')
                ->modalHeading(__('platform::console.overview.suspend.heading', ['name' => $name]))
                ->modalDescription(__('platform::console.overview.suspend.body'))
                ->modalSubmitActionLabel(__('platform::console.overview.suspend.confirm'))
                ->schema([
                    Textarea::make('reason')->label(__('platform::console.overview.suspend.reason'))
                        ->helperText(__('platform::console.overview.suspend.reason_hint'))->required()->minLength(5)->maxLength(500)->rows(3),
                ])
                ->action(function (array $data) {
                    app(SetAcademyStatus::class)->suspend($this->academy, (string) $data['reason']);
                    Notification::make()->title(__('platform::console.overview.suspend.done'))->success()->send();
                    $this->reloadPage();
                })
            : Action::make('reactivate')->label(__('platform::console.overview.reactivate.label'))->icon(Heroicon::OutlinedPlayCircle)->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('platform::console.overview.reactivate.heading', ['name' => $name]))
                ->modalDescription(__('platform::console.overview.reactivate.body'))
                ->modalSubmitActionLabel(__('platform::console.overview.reactivate.confirm'))
                ->action(function () {
                    app(SetAcademyStatus::class)->reactivate($this->academy);
                    Notification::make()->title(__('platform::console.overview.reactivate.done'))->success()->send();
                    $this->reloadPage();
                });

        return $actions;
    }

    /** The plan: who decides it, with its own limits and areas for this academy and the reason. */
    private function planAction(): Action
    {
        $l = 'platform::plans.change.';
        $current = SaasSubscription::currentFor($this->academy);
        $own = fn (Limit $limit) => TextInput::make('limits.'.$limit->value)->label(__('shared::plans.limits.'.$limit->value))
            ->numeric()->integer()->minValue(0)->placeholder(__($l.'follow_plan'))->suffix($limit === Limit::StorageMb ? __('platform::plans.catalog.form.mb') : null);

        return Action::make('plan')->label(__($l.'label'))->icon(Heroicon::OutlinedRectangleStack)->color('gray')
            ->modalHeading(__($l.'heading', ['name' => ConsoleView::name($this->record)]))
            ->modalDescription(__($l.'body'))
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->fillForm(fn () => [
                'plan' => $current?->plan_id ?? SaasPlan::default()?->id,
                'limits' => $current?->limits ?? [],
                'features_on' => array_values(array_intersect($current?->features_on ?? [], array_keys(PlanFormat::features('paid')))),
                'features_off' => array_values(array_intersect($current?->features_off ?? [], array_keys(PlanFormat::features('paid')))),
                'features_on_optional' => array_values(array_intersect($current?->features_on ?? [], array_keys(PlanFormat::features('optional')))),
                'features_off_optional' => array_values(array_intersect($current?->features_off ?? [], array_keys(PlanFormat::features('optional')))),
            ])
            ->schema([
                Select::make('plan')->label(__($l.'plan'))->required()->native(false)
                    ->options(fn () => SaasPlan::query()->where(fn ($q) => $q->whereNull('archived_at')->orWhere('id', $current?->plan_id))->orderBy('sort')->get()
                        ->mapWithKeys(fn (SaasPlan $p) => [$p->id => $p->name().(PlanFormat::price($p) ? ' · '.PlanFormat::price($p) : '')])->all()),
                Section::make(__($l.'own_limits'))->description(__($l.'own_limits_hint'))->compact()->collapsible()->collapsed(fn () => ($current?->limits ?? []) === [])->schema([
                    Grid::make(2)->schema([
                        $own(Limit::Teachers),
                        $own(Limit::ActiveStudents),
                        $own(Limit::VideoMinutes),
                        $own(Limit::StorageMb),
                        $own(Limit::AiUses),
                    ]),
                ]),
                Section::make(__($l.'own_features'))->compact()->collapsible()->collapsed(fn () => ($current?->features_on ?? []) === [] && ($current?->features_off ?? []) === [])->schema([
                    Grid::make(2)->schema([
                        CheckboxList::make('features_on')->label(__($l.'features_on').' · '.__($l.'kinds.paid'))->options(PlanFormat::features('paid')),
                        CheckboxList::make('features_off')->label(__($l.'features_off').' · '.__($l.'kinds.paid'))->options(PlanFormat::features('paid')),
                        // The free areas: the academy may hide them itself too; taken away here, it cannot bring them back.
                        CheckboxList::make('features_on_optional')->label(__($l.'features_on').' · '.__($l.'kinds.optional'))->options(PlanFormat::features('optional')),
                        CheckboxList::make('features_off_optional')->label(__($l.'features_off').' · '.__($l.'kinds.optional'))->options(PlanFormat::features('optional')),
                    ]),
                ]),
                Textarea::make('reason')->label(__('platform::plans.attributes.reason'))->helperText(__($l.'reason_hint'))
                    ->required()->minLength(ChangeAcademyPlan::REASON_MIN)->maxLength(300)->rows(2),
            ])
            ->action(function (array $data) use ($l) {
                $limits = [];
                foreach (($data['limits'] ?? []) as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $limits[$key] = (int) $value;
                    }
                }
                app(ChangeAcademyPlan::class)->handle($this->academy, (string) $data['plan'], (string) $data['reason'], $limits,
                    array_values([...($data['features_on'] ?? []), ...($data['features_on_optional'] ?? [])]),
                    array_values([...($data['features_off'] ?? []), ...($data['features_off_optional'] ?? [])]));
                Notification::make()->success()->title(__($l.'done'))->send();
                unset($this->plan);
            });
    }

    /** A bonus, a correction or a refund of the academy's balance, with a reason. */
    private function creditAction(): Action
    {
        $l = 'platform::metering.grant.';
        $meters = array_combine(array_map(fn (Meter $m) => $m->value, Meter::cases()), array_map(fn (Meter $m) => $m->label(), Meter::cases()));

        return Action::make('credit')->label(__($l.'label'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
            ->modalHeading(__($l.'heading', ['name' => ConsoleView::name($this->record)]))
            ->modalDescription(__($l.'body'))
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->schema([
                Grid::make(2)->schema([
                    Select::make('kind')->label(__($l.'kind'))->required()->native(false)->live()
                        ->options(['bonus' => __($l.'kinds.bonus'), 'adjust' => __($l.'kinds.adjust'), 'refund' => __($l.'kinds.refund')])->default('bonus'),
                    Select::make('meter')->label(__($l.'meter'))->required()->native(false)->live()->options($meters)->default(Meter::AiCredits->value),
                ]),
                Select::make('lot')->label(__($l.'lot'))->native(false)
                    ->visible(fn (Get $get) => $get('kind') === 'refund')->required(fn (Get $get) => $get('kind') === 'refund')
                    ->options(fn (Get $get) => $this->lotOptions((string) $get('meter'))),
                TextInput::make('amount')->label(__($l.'amount'))->helperText(fn (Get $get) => __($l.'amount_hint.'.($get('kind') ?: 'bonus')))
                    ->numeric()->integer()->required(),
                TextInput::make('months')->label(__($l.'months'))->numeric()->integer()->minValue(1)->maxValue(24)->default(12)
                    ->visible(fn (Get $get) => $get('kind') !== 'refund'),
                Textarea::make('reason')->label(__('platform::plans.attributes.reason'))->helperText(__($l.'reason_hint'))
                    ->required()->minLength(GrantUsage::REASON_MIN)->maxLength(300)->rows(2),
            ])
            ->action(function (array $data) use ($l): void {
                $admin = auth('platform')->user();
                abort_unless($admin instanceof PlatformAdmin, 403);
                app(GrantUsage::class)->handle($admin, $this->academy, $data);
                Notification::make()->success()->title(__($l.'done'))->send();
                unset($this->metering, $this->plan);
            });
    }

    /** Soft (no lesson cut, overage covered later) or hard (at zero, new lessons on the teacher's link). */
    private function hardLimitAction(): Action
    {
        $l = 'platform::metering.console.';
        $on = AcademyMetering::for($this->academy)->video_hard_limit;

        return Action::make('hardLimit')->label(__($l.($on ? 'hard_turn_off' : 'hard_turn_on')))->icon(Heroicon::OutlinedNoSymbol)->color('gray')
            ->modalHeading(__($l.($on ? 'hard_turn_off' : 'hard_turn_on')))
            ->modalDescription(__($l.($on ? 'hard_off_body' : 'hard_on_body')))
            ->schema([
                Textarea::make('reason')->label(__('platform::plans.attributes.reason'))->required()->minLength(5)->maxLength(300)->rows(2),
            ])
            ->action(function (array $data) use ($on, $l): void {
                $admin = auth('platform')->user();
                abort_unless($admin instanceof PlatformAdmin, 403);
                app(SetVideoHardLimit::class)->handle($admin, $this->academy, ! $on, (string) $data['reason']);
                Notification::make()->success()->title(__($l.'hard_saved'))->send();
                $this->reloadPage();
            });
    }

    /** @return array<string, string> the academy's bought lots with something left */
    private function lotOptions(string $meter): array
    {
        $m = Meter::tryFrom($meter);
        if ($m === null) {
            return [];
        }
        $tz = app(ViewerTimezone::class)->effective();
        $out = [];
        foreach (app(Meters::class)->state($this->academy, $m)->lots as $lot) {
            if ($lot['kind'] === 'purchase' && $lot['left'] > 0) {
                $out[$lot['id']] = __('platform::metering.kinds.purchase').' · '.MeterFormat::amount($m, $lot['left']).' · '.ConsoleView::date($lot['created_at'], $tz);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> each meter's ledger as the console reads it */
    #[Computed]
    public function metering(): array
    {
        if (SaasSubscription::currentFor($this->academy) === null) {
            return [];
        }
        $meters = app(Meters::class);
        $report = app(UsageReport::class);
        $tz = app(ViewerTimezone::class)->effective();
        $hard = AcademyMetering::for($this->academy)->video_hard_limit;
        $out = [];
        foreach (Meter::cases() as $meter) {
            $state = $meters->state($this->academy, $meter);
            $f = fn (int $n) => MeterFormat::amount($meter, $n);
            $out[] = [
                'key' => $meter->value,
                'label' => $meter->label(),
                'state' => ! $state->limited ? 'ok' : ($state->available() <= 0 ? 'full' : 'ok'),
                'allowance' => $state->limited ? __('platform::metering.card.allowance', ['used' => $f($state->allowanceUsed()), 'max' => $f($state->allowance)]) : __('platform::metering.card.unlimited_used', ['used' => $f($state->used)]),
                'bought' => $f($state->bought),
                'available' => $state->limited ? $f(max(0, $state->available())) : __('platform::metering.card.unlimited_short'),
                'debt' => $state->debt > 0 ? __('platform::metering.card.debt', ['n' => $f($state->debt)]) : null,
                'hard' => $meter === Meter::VideoMinutes && $hard,
                'lines' => array_map(fn (array $line) => [
                    'when' => ConsoleView::date($line['at'], $tz),
                    'what' => MeterFormat::line($line),
                    'amount' => MeterFormat::signed($meter, $line['amount']),
                    'sign' => $line['amount'] > 0 ? 'in' : 'out',
                    'after' => number_format($line['after']),
                ], $report->statement($this->academy, $meter, 6)),
            ];
        }

        return $out;
    }

    /** Counts this academy's use now, instead of waiting for the night's count. */
    public function countNow(): void
    {
        app(TenantContext::class)->run($this->record->toTenantRef(), fn () => app(MeasureUsage::class)->current());
        unset($this->plan);
    }

    /** @return ?array<string, mixed> */
    #[Computed]
    public function plan(): ?array
    {
        $subscription = SaasSubscription::currentFor($this->academy);
        if ($subscription === null) {
            return null;
        }
        $terms = PlanTerms::of($subscription);
        $counted = PlanSummary::counted($this->academy, $this->record->default_timezone, $terms);
        $tz = app(ViewerTimezone::class)->effective();

        return PlanSummary::of($terms, $counted['lines'], $counted['measured'], $subscription) + [
            'price' => PlanFormat::price($subscription->plan),
            'since' => ConsoleView::date($subscription->started_at, $tz),
            'measured_text' => $counted['measured'] ? ConsoleView::ago($counted['measured']->toIso8601String()) : null,
            'history' => array_map(fn (array $h) => $h + [
                'from_text' => ConsoleView::date($h['from'], $tz),
                'to_text' => $h['to'] ? ConsoleView::date($h['to'], $tz) : null,
            ], PlanSummary::history($this->academy)),
        ];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function usage(): array
    {
        $u = app(AcademyUsage::class)->forAcademies([$this->academy])[$this->academy];

        return $u + ['hours' => ConsoleView::hours($u['minutes']), 'money' => ConsoleView::money($u['received']), 'last' => ConsoleView::ago($u['last_lesson'])];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function managers(): array
    {
        return app(AcademyPeople::class)->managers($this->academy);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function activity(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $feed = app(AuditFeed::class)->entries($this->academy, limit: 12);

        return array_map(fn (object $row) => AuditEntries::describe($row, $tz, $feed['names']) + ['id' => $row->id], $feed['rows']);
    }

    /** @return list<array{host: string, verified: bool}> */
    #[Computed]
    public function hosts(): array
    {
        return $this->record->domains->sortBy(fn ($d) => $d->kind === DomainKind::Subdomain ? 0 : 1)
            ->map(fn ($d) => ['host' => $d->host, 'verified' => $d->verified_at !== null])->values()->all();
    }

    private function host(): string
    {
        return $this->hosts[0]['host'] ?? $this->record->slug;
    }

    /** The header's actions flip with the status, so the page loads again. */
    private function reloadPage(): void
    {
        $this->redirect(self::getUrl(['academy' => $this->academy]), navigate: true);
    }
}
