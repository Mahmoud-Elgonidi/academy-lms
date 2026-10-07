<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Payroll\Queries\LessonPayFacts;
use Modules\Payroll\Support\PayrollMonths;

/**
 * Runs the month's lessons through the ledger again. By default only the
 * lessons that have no line yet (no price on the card when they were
 * decided, or decided before payroll was set up) are priced; with $reprice
 * the whole month takes today's rate cards, which only an open month allows.
 */
final class RecalculatePayrollMonth
{
    public function __construct(
        private readonly AccrueLessonPay $accrue,
        private readonly LessonPayFacts $lessons,
        private readonly PayrollMonths $months,
    ) {}

    /** @return int how many lessons changed */
    public function handle(CarbonImmutable $month, bool $reprice, ?string $actorId): int
    {
        if ($reprice && $this->months->isClosed($month)) {
            throw ValidationException::withMessages(['month' => __('payroll::payroll.errors.closed')]);
        }
        [$from, $to] = $this->months->bounds($month);
        $changed = 0;
        foreach ($this->lessons->finalBetween($from, $to) as $sessionId) {
            $before = $this->line($sessionId);
            $after = $this->accrue->handle($sessionId, $reprice, $actorId)?->id;
            $changed += $before !== $after ? 1 : 0;
        }

        return $changed;
    }

    private function line(string $sessionId): ?string
    {
        return TeacherEarning::query()->live()->where('session_id', $sessionId)->where('kind', 'lesson')->value('id');
    }
}
