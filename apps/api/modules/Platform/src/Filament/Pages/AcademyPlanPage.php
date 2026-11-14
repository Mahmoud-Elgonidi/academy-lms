<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Actions\Plans\CancelPlanUpgrade;
use Modules\Platform\Actions\Plans\RequestPlanUpgrade;
use Modules\Platform\Actions\TopUps\CancelTopUpRequest;
use Modules\Platform\Actions\TopUps\RequestTopUp;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\PlanSummary;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Tenancy\TenantContext;
use UnitEnum;

/**
 * The academy's SaaS plan, for its owner and admins: how much of each limit
 * is used now (the monthly ones with what is left, this month's extra packs
 * and when they start again), and the areas it includes. Changing it is the
 * platform team's: from here the academy asks for an extra pack for this
 * month or a bigger plan, and follows its requests. The warning's buttons
 * open the same windows here (?buy=video_minutes|ai_uses, ?upgrade=1). The
 * meters in full (the bought balance, the charts, the ledger) are on the usage
 * page (UsagePage).
 */
final class AcademyPlanPage extends Page
{
    protected string $view = 'platform::plans.academy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 913;

    protected static ?string $slug = 'settings/plan';

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
        return __('platform::plans.academy.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::plans.academy.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::plans.academy.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('usage')->label(__('platform::metering.page.open'))->icon(Heroicon::OutlinedChartBar)->color('gray')
                ->url(UsagePage::getUrl()),
        ];
    }

    /** The warning's buttons: the window to open in place. */
    #[Url(except: '')]
    public string $buy = '';

    #[Url(except: '')]
    public string $upgrade = '';

    public function mount(): void
    {
        if (in_array($this->buy, [Limit::VideoMinutes->value, Limit::AiUses->value], true)) {
            $this->defaultAction = 'buy';
            $this->defaultActionArguments = ['limit' => $this->buy];
        } elseif ($this->upgrade !== '') {
            $this->defaultAction = 'upgrade';
        }
        $this->buy = $this->upgrade = '';
    }

    /** @return ?array<string, mixed> */
    #[Computed]
    public function plan(): ?array
    {
        $usage = app(PlanUsage::class);
        $terms = $usage->terms();

        if ($terms === null) {
            return null;
        }
        // Monthly limits start again on the first of next month, on the academy's clock.
        $renews = PlanRequests::renews(app(TenantContext::class)->require()->defaultTimezone);

        return PlanSummary::of($terms, $usage->lines(), renews: $renews) + ['waiting' => $this->waitingLimits()];
    }

    /**
     * This month's extra packs (and any still waiting from before), newest first, and the open request for a bigger plan.
     *
     * @return array{topups: list<array<string, mixed>>, upgrade: ?array<string, mixed>}
     */
    #[Computed]
    public function requests(): array
    {
        $academy = app(TenantContext::class)->require();
        $month = PlanRequests::monthKey($academy->defaultTimezone);
        $topups = UsageTopUp::query()->where('academy_id', $academy->id)
            ->where(fn ($q) => $q->where('period', $month)->orWhere('status', 'requested'))
            ->orderByDesc('created_at')->limit(20)->get()
            ->map(fn (UsageTopUp $t) => [
                'id' => $t->id,
                'what' => __('shared::plans.limits.'.$t->limit),
                'amount' => '+'.PlanFormat::amount($t->limitKind(), $t->amount),
                'status' => $t->status,
                'status_text' => __('platform::topups.status.'.$t->status),
                'reason' => $t->status === 'declined' ? $t->reason : null,
                'when' => $t->created_at->setTimezone($academy->defaultTimezone)->locale(app()->getLocale())->translatedFormat('j F'),
            ])->all();
        $open = PlanUpgradeRequest::openFor($academy->id);

        return ['topups' => $topups, 'upgrade' => $open === null ? null : [
            'id' => $open->id,
            'plan' => $open->plan?->name(),
            'when' => $open->created_at->setTimezone($academy->defaultTimezone)->locale(app()->getLocale())->translatedFormat('j F'),
        ]];
    }

    /** Ask for an extra pack of one monthly limit, for this month. */
    public function buyAction(): Action
    {
        $l = 'platform::topups.buy.';

        return Action::make('buy')->label(__($l.'label'))->icon(Heroicon::OutlinedPlusCircle)->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'heading', ['what' => __('shared::plans.limits.'.$this->limitOf($arguments)->value)]))
            ->modalDescription(fn () => __($l.'body', ['renews' => PlanRequests::renews(app(TenantContext::class)->require()->defaultTimezone)]))
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->visible(fn (array $arguments) => ! in_array($this->limitOf($arguments)->value, $this->waitingLimits(), true))
            ->schema(fn (array $arguments) => [
                Radio::make('pack')->label(__($l.'pack'))->required()
                    ->options(fn () => $this->packOptions($this->limitOf($arguments))),
                Textarea::make('note')->label(__('platform::topups.fields.note'))->helperText(__($l.'note_hint'))->maxLength(RequestTopUp::NOTE_MAX)->rows(2),
            ])
            ->fillForm(fn (array $arguments) => ['pack' => array_key_first($this->packOptions($this->limitOf($arguments)))])
            ->action(function (array $data): void {
                app(RequestTopUp::class)->handle((string) $data['pack'], $data['note'] ?? null, (string) auth()->id());
                Notification::make()->success()->title(__('platform::topups.buy.done'))->body(__('platform::topups.buy.done_body'))->send();
                $this->refreshRequests();
            });
    }

    /** Ask the platform team for a bigger plan. */
    public function upgradeAction(): Action
    {
        $l = 'platform::topups.upgrade.';

        return Action::make('upgrade')->label(__($l.'label'))->icon(Heroicon::OutlinedArrowTrendingUp)->color('gray')->size('sm')
            ->modalHeading(__($l.'heading'))
            ->modalDescription(__($l.'body'))
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->visible(fn () => $this->requests['upgrade'] === null)
            ->schema([
                Select::make('plan')->label(__($l.'plan'))->native(false)->placeholder(__($l.'any_plan'))
                    ->options(fn () => $this->upgradeOptions()),
                Textarea::make('note')->label(__('platform::topups.fields.note'))->helperText(__($l.'note_hint'))->maxLength(RequestPlanUpgrade::NOTE_MAX)->rows(3),
            ])
            ->action(function (array $data): void {
                app(RequestPlanUpgrade::class)->handle($data['plan'] ?? null, $data['note'] ?? null, (string) auth()->id());
                Notification::make()->success()->title(__('platform::topups.upgrade.done'))->send();
                $this->refreshRequests();
            });
    }

    public function cancelTopUpAction(): Action
    {
        return Action::make('cancelTopUp')->label(__('platform::topups.cancel.label'))->color('gray')->size('xs')->link()
            ->requiresConfirmation()->modalHeading(__('platform::topups.cancel.heading'))
            ->action(function (array $arguments): void {
                app(CancelTopUpRequest::class)->handle((string) $arguments['topup']);
                Notification::make()->success()->title(__('platform::topups.cancel.done'))->send();
                $this->refreshRequests();
            });
    }

    public function cancelUpgradeAction(): Action
    {
        return Action::make('cancelUpgrade')->label(__('platform::topups.cancel.label'))->color('gray')->size('xs')->link()
            ->requiresConfirmation()->modalHeading(__('platform::topups.cancel.upgrade_heading'))
            ->action(function (array $arguments): void {
                app(CancelPlanUpgrade::class)->handle((string) $arguments['request']);
                Notification::make()->success()->title(__('platform::topups.cancel.done'))->send();
                $this->refreshRequests();
            });
    }

    /** @param  array<string, mixed>  $arguments */
    private function limitOf(array $arguments): Limit
    {
        $limit = Limit::tryFrom((string) ($arguments['limit'] ?? ''));

        return $limit !== null && $limit->monthly() ? $limit : Limit::VideoMinutes;
    }

    /** @return array<string, string> pack id => "Medium · 600 minutes · SAR 50.00" */
    private function packOptions(Limit $limit): array
    {
        return TopUpPack::query()->offered()->where('limit', $limit->value)->orderBy('sort')->orderBy('amount')->get()
            ->mapWithKeys(fn (TopUpPack $p) => [$p->id => implode(' · ', array_filter([
                $p->name(), '+'.PlanFormat::amount($limit, $p->amount),
                $p->priceIn((string) Academy::query()->whereKey(app(TenantContext::class)->require()->id)->value('base_currency'))?->format(),
            ]))])->all();
    }

    /** @return array<string, string> the plans on offer other than this one */
    private function upgradeOptions(): array
    {
        $current = SaasSubscription::currentFor(app(TenantContext::class)->require()->id)?->plan_id;

        return SaasPlan::query()->offered()->where('shown_on_site', true)->when($current, fn ($q) => $q->whereKeyNot($current))
            ->orderBy('sort')->get()
            ->mapWithKeys(fn (SaasPlan $p) => [$p->id => $p->name().(PlanFormat::price($p) ? ' · '.PlanFormat::price($p).' '.__('platform::plans.catalog.per_month') : '')])->all();
    }

    /** @return list<string> the limits with an extra pack waiting for the platform team */
    private function waitingLimits(): array
    {
        return UsageTopUp::query()->where('academy_id', app(TenantContext::class)->require()->id)->where('status', 'requested')->pluck('limit')->all();
    }

    private function refreshRequests(): void
    {
        unset($this->plan, $this->requests);
    }
}
