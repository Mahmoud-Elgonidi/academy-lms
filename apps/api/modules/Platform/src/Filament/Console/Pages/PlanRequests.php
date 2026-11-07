<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Actions\Plans\HandlePlanUpgrade;
use Modules\Platform\Actions\TopUps\ActivateTopUp;
use Modules\Platform\Actions\TopUps\DeclineTopUp;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Plans\PlanFormat;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * What academies asked about their plans, across academies: extra packs of
 * video minutes or AI uses for the month (turned on once paid, the platform
 * team's own way, or turned down with a reason the academy reads), and
 * requests for a bigger plan (changed on the academy's page, then marked
 * handled). Waiting ones first.
 */
final class PlanRequests extends Page
{
    protected string $view = 'platform::console.plan-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'plan-requests';

    #[Url]
    public string $status = 'waiting';

    public static function getNavigationLabel(): string
    {
        return __('platform::topups.console.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = UsageTopUp::query()->where('status', 'requested')->count() + PlanUpgradeRequest::query()->where('status', 'requested')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::topups.console.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::topups.console.subtitle').' '.ZoneHint::yours();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function requests(): array
    {
        $waiting = $this->status !== 'all';
        $topups = UsageTopUp::query()->with('pack')->when($waiting, fn ($q) => $q->where('status', 'requested'))->latest()->limit(200)->get();
        $upgrades = PlanUpgradeRequest::query()->with(['plan', 'fromPlan'])->when($waiting, fn ($q) => $q->where('status', 'requested'))->latest()->limit(200)->get();
        $academies = Academy::query()->whereIn('id', $topups->pluck('academy_id')->merge($upgrades->pluck('academy_id'))->unique()->all())->get()->keyBy('id');
        $people = DB::connection('pgsql_platform')->table('users')->whereIn('id', $topups->pluck('requested_by')->merge($upgrades->pluck('requested_by'))->filter()->unique()->all())->pluck('name', 'id');
        $admins = PlatformAdmin::query()->pluck('name', 'id');
        $tz = app(ViewerTimezone::class)->effective();
        $when = fn ($at) => $at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M Y، H:i'));
        $common = fn ($r) => [
            'id' => $r->id,
            'academy' => isset($academies[$r->academy_id]) ? ConsoleView::name($academies[$r->academy_id]) : '—',
            'academy_url' => AcademyOverview::getUrl(['academy' => $r->academy_id]),
            'by' => $people[$r->requested_by] ?? null,
            'note' => $r->note,
            'status' => $r->status,
            'when' => $when($r->created_at),
            'ago' => $r->created_at->locale(app()->getLocale())->diffForHumans(),
            'at' => $r->created_at->getTimestamp(),
        ];

        $rows = [];
        foreach ($topups as $t) {
            $rows[] = $common($t) + [
                'kind' => 'topup',
                'what' => __('shared::plans.limits.'.$t->limit).': +'.PlanFormat::amount($t->limitKind(), $t->amount)
                    .($t->pack ? ' · '.$t->pack->name().($t->pack->price_minor !== null && $t->pack->currency ? ' · '.Money::of($t->pack->price_minor, $t->pack->currency)->format() : '') : ''),
                'answer' => match ($t->status) {
                    'active' => __('platform::topups.console.activated', ['name' => $admins[$t->decided_by] ?? '—', 'month' => CarbonImmutable::parse($t->period)->locale(app()->getLocale())->translatedFormat('F Y')]),
                    'declined' => __('platform::topups.console.declined', ['name' => $admins[$t->decided_by] ?? '—', 'reason' => (string) $t->reason]),
                    default => null,
                },
            ];
        }
        foreach ($upgrades as $u) {
            $rows[] = $common($u) + [
                'kind' => 'upgrade',
                'what' => __('platform::topups.console.upgrade', ['from' => $u->fromPlan?->name() ?? '—', 'to' => $u->plan?->name() ?? __('platform::topups.console.any_plan')]),
                'answer' => $u->status === 'handled' ? __('platform::topups.console.handled', ['name' => $admins[$u->handled_by] ?? '—']) : null,
            ];
        }
        usort($rows, fn ($a, $b) => [$a['status'] !== 'requested', -$a['at']] <=> [$b['status'] !== 'requested', -$b['at']]);

        return $rows;
    }

    public function activateAction(): Action
    {
        return Action::make('activate')->label(__('platform::topups.console.activate'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('platform::topups.console.activate'))
            ->modalDescription(__('platform::topups.console.activate_body'))
            ->action(function (array $arguments): void {
                app(ActivateTopUp::class)->handle($this->admin(), (string) $arguments['topup']);
                $this->answered();
            });
    }

    public function declineAction(): Action
    {
        return Action::make('decline')->label(__('platform::topups.console.decline'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalHeading(__('platform::topups.console.decline'))->modalSubmitActionLabel(__('platform::topups.console.decline'))
            ->schema([Textarea::make('reason')->label(__('platform::plans.attributes.reason'))->helperText(__('platform::topups.console.reason_hint'))
                ->required()->minLength(DeclineTopUp::REASON_MIN)->maxLength(300)->rows(3)])
            ->action(function (array $data, array $arguments): void {
                app(DeclineTopUp::class)->handle($this->admin(), (string) $arguments['topup'], (string) $data['reason']);
                $this->answered();
            });
    }

    public function handledAction(): Action
    {
        return Action::make('handled')->label(__('platform::topups.console.mark_handled'))->icon(Heroicon::OutlinedCheck)->color('gray')->size('sm')
            ->requiresConfirmation()->modalDescription(__('platform::topups.console.handled_body'))
            ->action(function (array $arguments): void {
                app(HandlePlanUpgrade::class)->handle($this->admin(), (string) $arguments['request']);
                $this->answered();
            });
    }

    private function admin(): PlatformAdmin
    {
        $admin = auth('platform')->user();
        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    private function answered(): void
    {
        unset($this->requests);
        Notification::make()->success()->title(__('platform::topups.console.answered'))->send();
    }
}
