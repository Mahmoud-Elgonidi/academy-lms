<?php

namespace Modules\Billing\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Billing\Actions\ExportRevenue;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Live\RevenueChanged;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Billing\Support\RevenuePeriod;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The academy's revenue (docs/architecture/01, section 17): what came in
 * over a period and against the one before, what was invoiced, what is owed
 * and late now, where the money came from (how it was paid, new courses or
 * renewals, subjects, plans), the latest payments, the late invoices and the
 * renewals coming. One currency at a time; it follows invoices live.
 */
final class Revenue extends Page
{
    /** Renewals listed on the screen (the rest are counted). */
    public const FORECAST_SHOWN = 8;

    protected string $view = 'billing::filament.revenue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -3;

    protected static ?string $slug = 'revenue';

    #[Url]
    public string $range = 'this_month';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?string $currency = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewBilling->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::revenue.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::revenue.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing::revenue.subtitle');
    }

    public function liveChannel(): string
    {
        return RevenueChanged::channel((string) app(TenantContext::class)->id());
    }

    #[Computed]
    public function period(): RevenuePeriod
    {
        return app(RevenueBoard::class)->period($this->range, $this->from, $this->to);
    }

    /** @return list<string> */
    #[Computed]
    public function currencies(): array
    {
        return app(RevenueBoard::class)->currencies();
    }

    /** The currency on screen: the one asked for if the academy uses it, else its most used. */
    public function code(): ?string
    {
        return in_array($this->currency, $this->currencies, true) ? $this->currency : ($this->currencies[0] ?? null);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function summary(): ?array
    {
        return ($code = $this->code()) === null ? null : app(RevenueBoard::class)->summary($this->period, $code);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function bars(): array
    {
        $code = $this->code();
        if ($code === null) {
            return [];
        }
        $step = $this->period->step();
        $series = app(RevenueBoard::class)->series($this->period, $code);

        return array_map(fn (array $bar) => $bar + [
            'label' => $this->barLabel($bar['first'], $bar['last'], $step),
            'collected_text' => Money::of($bar['collected'], $code)->format(),
            'invoiced_text' => Money::of($bar['invoiced'], $code)->format(),
        ], $series);
    }

    /** @return array<string, list<array<string, mixed>>> */
    #[Computed]
    public function breakdown(): array
    {
        $code = $this->code();

        return $code === null ? ['methods' => [], 'kinds' => [], 'subjects' => [], 'plans' => []] : app(RevenueBoard::class)->breakdown($this->period, $code);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function recent(): array
    {
        $code = $this->code();

        return $code === null ? [] : array_map(fn (array $p) => $p + [
            'url' => InvoiceResource::getUrl('view', ['record' => $p['invoice_id']]),
            'amount' => Money::of($p['minor'], $code)->format(),
            'when' => $this->moment($p['received_at']),
        ], app(RevenueBoard::class)->recentPayments($this->period, $code));
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function overdue(): array
    {
        $code = $this->code();

        return $code === null ? [] : array_map(fn (array $i) => $i + [
            'url' => InvoiceResource::getUrl('view', ['record' => $i['id']]),
            'amount' => Money::of($i['owed'], $code)->format(),
        ], app(RevenueBoard::class)->overdue($code));
    }

    /** @return array{total: Money, count: int, days: int, items: list<array<string, mixed>>}|null items: the first FORECAST_SHOWN, ready to show */
    #[Computed]
    public function forecast(): ?array
    {
        $code = $this->code();
        if ($code === null) {
            return null;
        }
        $forecast = app(RevenueBoard::class)->forecast($code);
        $today = CarbonImmutable::parse(CarbonImmutable::now()->setTimezone($this->period->timezone)->toDateString());
        $forecast['items'] = array_map(fn (array $i) => $i + [
            'amount' => Money::of($i['minor'], $code)->format(),
            'when' => $i['on']->lessThanOrEqualTo($today) ? __('billing::revenue.forecast.now')
                : __('billing::revenue.forecast.on', ['date' => $i['on']->locale(app()->getLocale())->translatedFormat('j M')]),
        ], array_slice($forecast['items'], 0, self::FORECAST_SHOWN));

        return $forecast;
    }

    public function setRange(string $range): void
    {
        if (! in_array($range, RevenuePeriod::PRESETS, true)) {
            return;
        }
        if ($range === 'custom') {
            // Start from the days on screen, to be changed.
            $this->from = $this->period->first->toDateString();
            $this->to = $this->period->last->toDateString();
        } else {
            $this->from = $this->to = null;
        }
        $this->range = $range;
        $this->forget();
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
        $this->forget();
    }

    public function updatedFrom(): void
    {
        $this->forget();
    }

    public function updatedTo(): void
    {
        $this->forget();
    }

    /** "1 – 9 Oct 2026", "28 Sep – 9 Oct 2026", "1 Nov 2025 – 9 Oct 2026". */
    public function periodLabel(RevenuePeriod $period): string
    {
        $locale = app()->getLocale();
        $first = $period->first->locale($locale);
        $last = $period->last->locale($locale);
        if ($period->days() === 1) {
            return $last->translatedFormat('j F Y');
        }
        $start = match (true) {
            $first->year !== $last->year => $first->translatedFormat('j F Y'),
            $first->month !== $last->month => $first->translatedFormat('j F'),
            default => $first->translatedFormat('j'),
        };

        return $start.' – '.$last->translatedFormat('j F Y');
    }

    /** @return array{tone: string, text: string}|null how a flow compares with the period before */
    public function change(Money $now, Money $before): ?array
    {
        if ($before->minor === 0) {
            return $now->minor === 0 ? null : ['tone' => 'up', 'text' => __('billing::revenue.tiles.new')];
        }
        $pct = (int) round(($now->minor - $before->minor) * 100 / $before->minor);

        return match (true) {
            $pct > 0 => ['tone' => 'up', 'text' => __('billing::revenue.tiles.up', ['pct' => $pct])],
            $pct < 0 => ['tone' => 'down', 'text' => __('billing::revenue.tiles.down', ['pct' => -$pct])],
            default => ['tone' => 'same', 'text' => __('billing::revenue.tiles.same')],
        };
    }

    public function invoicesUrl(string $tab): string
    {
        return InvoiceResource::getUrl('index', ['tab' => $tab]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('billing::revenue.export.action'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn () => $this->code() !== null)
                ->action(function () {
                    $export = app(ExportRevenue::class);
                    $code = (string) $this->code();

                    return response()->download($export->handle($this->period, $code), $export->filename($this->period, $code))->deleteFileAfterSend();
                }),
        ];
    }

    private function forget(): void
    {
        unset($this->period, $this->summary, $this->bars, $this->breakdown, $this->recent, $this->overdue, $this->forecast);
    }

    private function barLabel(CarbonImmutable $first, CarbonImmutable $last, string $step): string
    {
        $locale = app()->getLocale();

        return match ($step) {
            'day' => $first->locale($locale)->translatedFormat('j M'),
            'week' => $first->locale($locale)->translatedFormat('j M').' – '.$last->locale($locale)->translatedFormat('j M'),
            default => $first->locale($locale)->translatedFormat('M Y'),
        };
    }

    /** When a payment arrived, on the viewer's clock: "9 Oct, 2:05 PM" (or "9 Oct, 14:05"). */
    private function moment(CarbonImmutable $at): string
    {
        $ar = app()->getLocale() === 'ar';

        return $at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat(Clock::pattern($ar ? 'j M، H:i' : 'j M, H:i'));
    }
}
