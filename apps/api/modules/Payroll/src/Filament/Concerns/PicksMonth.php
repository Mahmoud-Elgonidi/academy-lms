<?php

namespace Modules\Payroll\Filament\Concerns;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Modules\Payroll\Support\PayrollMonths;

/** A screen about one payroll month, chosen in the address (?month=2026-09) and stepped a month at a time. */
trait PicksMonth
{
    #[Url]
    public ?string $month = null;

    public function monthStart(): CarbonImmutable
    {
        return app(PayrollMonths::class)->parse($this->month);
    }

    public function monthLabel(): string
    {
        return PayrollMonths::label($this->monthStart());
    }

    public function stepMonth(int $by): void
    {
        $next = $this->monthStart()->addMonths(max(-1, min(1, $by)));
        $this->month = $next->greaterThan(app(PayrollMonths::class)->current()) ? $this->month : $next->format('Y-m');
        $this->forgetMonth();
    }

    public function isCurrentMonth(): bool
    {
        return $this->monthStart()->equalTo(app(PayrollMonths::class)->current());
    }

    /** Drops what was computed for the month on screen. */
    abstract protected function forgetMonth(): void;
}
