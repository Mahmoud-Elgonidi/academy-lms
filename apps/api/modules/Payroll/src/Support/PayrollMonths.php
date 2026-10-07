<?php

namespace Modules\Payroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Payroll months are calendar months on the academy's clock: a lesson on
 * the evening of 31 October in Cairo is October's, whatever UTC says. A
 * month is open until it is closed; what lands after that is paid in the
 * first month still open.
 */
final class PayrollMonths
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function timezone(): string
    {
        return $this->tenant->require()->defaultTimezone;
    }

    /** The first day of the month the instant falls in, on the academy's clock. */
    public function of(CarbonImmutable $instant): CarbonImmutable
    {
        return CarbonImmutable::parse($instant->setTimezone($this->timezone())->format('Y-m-01'), 'UTC');
    }

    public function current(?CarbonImmutable $now = null): CarbonImmutable
    {
        return $this->of($now ?? CarbonImmutable::now());
    }

    /** "2026-09" (or a date in it) → its first day; anything else → the current month. */
    public function parse(?string $month): CarbonImmutable
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])(-\d{2})?$/', $month)
            ? CarbonImmutable::parse(substr($month, 0, 7).'-01', 'UTC')
            : $this->current();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the month's first and next month's first instant, in UTC */
    public function bounds(CarbonImmutable $month): array
    {
        $start = CarbonImmutable::parse($month->format('Y-m-01').' 00:00', $this->timezone());

        return [$start->utc(), $start->addMonth()->utc()];
    }

    public function isClosed(CarbonImmutable $month): bool
    {
        return PayrollPeriod::query()->whereDate('month', $month->format('Y-m-01'))->exists();
    }

    /** The month itself if open, else the first open one after it. */
    public function firstOpenFrom(CarbonImmutable $month): CarbonImmutable
    {
        $closed = PayrollPeriod::query()->whereDate('month', '>=', $month->format('Y-m-01'))->pluck('month')
            ->map(fn ($m) => CarbonImmutable::parse($m)->format('Y-m'))->flip();
        while ($closed->has($month->format('Y-m'))) {
            $month = $month->addMonth();
        }

        return $month;
    }

    /** One payroll writer at a time per academy, so a month is not closed while a line goes into it. */
    public function lock(): void
    {
        DB::select("select pg_advisory_xact_lock(hashtextextended('payroll:' || ?, 0))", [$this->tenant->require()->id]);
    }

    /** "سبتمبر 2026" / "September 2026". */
    public static function label(CarbonImmutable $month, ?string $locale = null): string
    {
        return $month->locale($locale ?? app()->getLocale())->translatedFormat('F Y');
    }
}
