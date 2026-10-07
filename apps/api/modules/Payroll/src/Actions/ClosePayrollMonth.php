<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Contracts\Events\PayrollMonthClosed;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Support\PayrollMonths;

/**
 * Closes a payroll month once it is over: its lines are final (the database
 * takes no more for it) and its export no longer changes. Lessons of that
 * month decided later, and corrections to it, are paid in the next open
 * month. Months close in order; there is no reopening.
 */
final class ClosePayrollMonth
{
    public function __construct(private readonly PayrollMonths $months) {}

    public function handle(CarbonImmutable $month, string $actorId, ?CarbonImmutable $now = null): PayrollPeriod
    {
        $month = $this->months->parse($month->format('Y-m'));
        if ($month->greaterThanOrEqualTo($this->months->current($now))) {
            throw ValidationException::withMessages(['month' => __('payroll::payroll.errors.not_over')]);
        }

        return DB::transaction(function () use ($month, $actorId, $now) {
            $this->months->lock();
            if ($this->months->isClosed($month)) {
                throw ValidationException::withMessages(['month' => __('payroll::payroll.errors.closed')]);
            }
            $earlier = TeacherEarning::query()->whereDate('period', '<', $month->format('Y-m-d'))
                ->whereNotIn('period', PayrollPeriod::query()->select('month'))->min('period');
            if ($earlier !== null) {
                throw ValidationException::withMessages(['month' => __('payroll::payroll.errors.close_earlier', ['month' => PayrollMonths::label(CarbonImmutable::parse($earlier))])]);
            }

            $period = PayrollPeriod::query()->create(['month' => $month, 'closed_at' => $now ?? CarbonImmutable::now(), 'closed_by' => $actorId]);
            DB::afterCommit(fn () => event(new PayrollMonthClosed($period->academy_id, $month->format('Y-m-d'), $actorId)));

            return $period;
        });
    }
}
