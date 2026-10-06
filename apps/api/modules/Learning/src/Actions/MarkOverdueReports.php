<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Contracts\Events\SessionReportOverdue;
use Modules\Learning\Models\SessionReport;

/** Sweeper: reports past their deadline become overdue (they can still be handed in, marked late). */
final class MarkOverdueReports
{
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $reports = SessionReport::query()->where('status', 'due')->where('due_at', '<', $now)->get();

        foreach ($reports as $report) {
            DB::transaction(function () use ($report) {
                $report->update(['status' => 'overdue']);
                DB::afterCommit(fn () => event(new SessionReportOverdue($report->academy_id, $report->id, $report->session_id, $report->teacher_id)));
            });
        }

        return $reports->count();
    }
}
