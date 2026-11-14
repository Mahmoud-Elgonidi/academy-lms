<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Console\RechargeReport;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Metering\MeterFormat;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Console › Usage and revenue (owners only; docs/architecture/02, section
 * 11, "متابعة مالك المنصة"): what the extra packs sold and brought in, each
 * currency on its own, the paid balance not used yet and what of it ends
 * within 30 days, the recharges that did not go through, the latest
 * receipts, and each academy's month. Everything is read each time
 * (Console\RechargeReport).
 */
final class RechargeRevenuePage extends Page
{
    public const PERIODS = ['30d', '12m', 'all'];

    protected string $view = 'platform::console.recharge-revenue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'usage-revenue';

    #[Url(except: '30d')]
    public string $period = '30d';

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::recharge.console.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::recharge.console.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::recharge.console.subtitle');
    }

    public function showPeriod(string $period): void
    {
        if (in_array($period, self::PERIODS, true)) {
            $this->period = $period;
            unset($this->totals);
        }
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $report = app(RechargeReport::class);
        $since = match ($this->period) {
            '12m' => CarbonImmutable::now()->subMonths(12),
            'all' => null,
            default => CarbonImmutable::now()->subDays(30),
        };
        $locale = app()->getLocale();
        $tz = app(ViewerTimezone::class)->effective();
        $academies = Academy::query()->get(['id', 'name_ar', 'name_en'])->keyBy('id');
        $sold = $report->sold($since);
        $unused = $report->unused();
        $byCurrency = $report->revenueByCurrency($since);

        return [
            'sold' => array_map(fn (Meter $m) => [
                'label' => $m->label(),
                'amount' => MeterFormat::amount($m, $sold[$m->limit()->value]['amount'] ?? 0),
                'purchases' => trans_choice('platform::recharge.console.purchases', $sold[$m->limit()->value]['purchases'] ?? 0, ['count' => $sold[$m->limit()->value]['purchases'] ?? 0]),
            ], Meter::cases()),
            'by_currency' => array_map(fn (string $c, int $minor) => Money::of($minor, $c)->format($locale), array_keys($byCurrency), $byCurrency),
            'revenue' => array_map(fn (array $r) => [
                'pack' => ($locale === 'ar' ? $r['pack_ar'] : $r['pack_en']).' · '.__('shared::plans.limits.'.$r['limit']),
                'currency' => $r['currency'],
                'purchases' => $r['purchases'],
                'total' => Money::of($r['minor'], $r['currency'])->format($locale),
            ], $report->revenue($since)),
            'unused' => array_map(fn (Meter $m) => [
                'label' => $m->label(),
                'left' => MeterFormat::amount($m, $unused[$m->value]['left']),
                'expiring' => __('platform::recharge.console.expiring', ['n' => MeterFormat::amount($m, $unused[$m->value]['expiring'])]),
            ], Meter::cases()),
            'failed' => array_map(fn (TopUpPurchase $p) => [
                'id' => $p->id,
                'academy' => isset($academies[$p->academy_id]) ? ConsoleView::name($academies[$p->academy_id]) : '—',
                'academy_url' => AcademyOverview::getUrl(['academy' => $p->academy_id]),
                'what' => __('shared::plans.limits.'.$p->limit).' · +'.MeterFormat::amount($p->meter(), $p->amount).' · '.$p->price()->format($locale),
                'method' => __('platform::recharge.methods.'.$p->method),
                'why' => $p->failure !== null ? __('platform::recharge.declines.'.$p->failure) : '—',
                'when' => ($p->failed_at ?? $p->created_at)->setTimezone($tz)->locale($locale)->translatedFormat(Clock::pattern('j M Y · H:i')),
            ], $report->failed($since)),
            'receipts' => array_map(fn (PlatformReceipt $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'academy' => isset($academies[$r->academy_id]) ? ConsoleView::name($academies[$r->academy_id]) : $r->academy_name,
                'what' => $r->packName($locale).' · +'.MeterFormat::amount($r->meter(), $r->amount),
                'method' => __('platform::recharge.methods.'.$r->method),
                'amount' => $r->price()->format($locale),
                'when' => ConsoleView::date($r->paid_at, $tz),
                'url' => route('console.receipt', ['receipt' => $r->id]),
            ], $report->latestReceipts()),
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function academies(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $l = 'platform::recharge.console.';

        return array_map(function (array $row) use ($tz, $l) {
            $meter = function (Meter $m) use ($row, $l): array {
                $s = $row['meters'][$m->value];
                if ($s === null || ! $s['limited']) {
                    return ['allowance' => $s === null ? '—' : __($l.'unlimited'), 'bought' => '—', 'debt' => null];
                }

                return [
                    'allowance' => number_format($s['used']).' / '.MeterFormat::amount($m, $s['allowance']),
                    'bought' => MeterFormat::amount($m, $s['bought']),
                    'debt' => $s['debt'] > 0 ? MeterFormat::amount($m, $s['debt']) : null,
                ];
            };

            return [
                'id' => $row['academy']->id,
                'name' => ConsoleView::name($row['academy']),
                'url' => AcademyOverview::getUrl(['academy' => $row['academy']->id]),
                'plan' => $row['plan'] ?? __($l.'no_plan'),
                'video' => $meter(Meter::VideoMinutes),
                'ai' => $meter(Meter::AiCredits),
                'auto' => $row['auto'] ? __($l.'auto_on') : __($l.'auto_off'),
                'last' => $row['last'] === null ? __($l.'never') : ConsoleView::date($row['last'], $tz),
            ];
        }, app(RechargeReport::class)->academies());
    }
}
