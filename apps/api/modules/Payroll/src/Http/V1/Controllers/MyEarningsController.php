<?php

namespace Modules\Payroll\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payroll\Filament\PayrollView;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The teacher app: my pay for a month (on the academy's clock), lesson by lesson. Never shown to families. */
final class MyEarningsController
{
    /**
     * My earnings.
     *
     * month is "YYYY-MM" (default: this month). Closed months no longer change; an open one may still gain lessons.
     */
    public function show(Request $request, PayrollMonth $payroll, PayrollMonths $months, ViewerTimezone $viewer, LocalTimePresenter $time): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $month = $months->parse($request->query('month'));
        $tz = $viewer->effective();
        $statement = $payroll->statement($month, $me);
        $state = $payroll->state($month);
        $money = fn (Money $m) => ['minor' => $m->minor, 'currency' => $m->currency, 'formatted' => $m->format()];

        return response()->json([
            'month' => $month->format('Y-m'),
            'label' => PayrollMonths::label($month),
            'closed' => $state['closed'],
            'closed_at' => $time->present($state['closed_at']),
            /** Lessons of the month still waiting for their outcome. */
            'pending' => $state['pending'],
            /** Lessons held but not priced: the academy has no rate for them yet. */
            'unpriced' => $payroll->unpriced($month, $me)[$me] ?? 0,
            /** One per currency. */
            'totals' => array_map(fn (array $r) => [
                'currency' => $r['currency'],
                'lessons' => $r['lessons'],
                'hours' => PayrollView::hours($r['minutes']),
                'lesson_pay' => $money($r['lesson_pay']),
                'bonuses' => $money($r['bonuses']),
                'deductions' => $money($r['deductions']),
                'total' => $money($r['total']),
            ], $payroll->teachers($month, $me)),
            'lessons' => array_map(fn (array $r) => PayrollView::lesson($r, $tz), $statement['lessons']),
            'adjustments' => array_map(fn (array $r) => PayrollView::adjustment($r, $tz), $statement['adjustments']),
        ]);
    }
}
