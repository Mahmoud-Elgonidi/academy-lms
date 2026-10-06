<?php

namespace Modules\Learning\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Domain\NodeProgress;

/** The projected progress of an enrollment, and how many lessons a week it has. */
final class ProgressQuery
{
    /** @return array<string, NodeProgress> by lesson id */
    public function states(string $enrollmentId): array
    {
        $at = fn (?string $v) => $v === null ? null : CarbonImmutable::parse($v);

        return DB::table('enrollment_progress')->where('enrollment_id', $enrollmentId)->get()
            ->mapWithKeys(fn (object $r) => [$r->node_id => new NodeProgress(
                $r->node_id, $r->status, (int) $r->times_seen, (int) $r->high_scores, $r->last_score === null ? null : (int) $r->last_score,
                $r->last_outcome, $at($r->first_seen_at), $at($r->mastered_at),
                $r->review_interval_days === null ? null : (int) $r->review_interval_days, $at($r->next_review_at),
            )])
            ->all();
    }

    /** Weekly times in force, else the plan's lessons a week. */
    public function lessonsPerWeek(string $enrollmentId): int
    {
        $slots = DB::table('recurring_slots')->where('enrollment_id', $enrollmentId)->where('status', 'active')
            ->whereRaw('(upper_inf(effective) or upper(effective) > current_date)')->count();
        if ($slots > 0) {
            return $slots;
        }

        return (int) DB::table('subscriptions as s')
            ->join('plans as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.id', '=', 's.plan_id'))
            ->where('s.enrollment_id', $enrollmentId)->where('s.status', '!=', 'cancelled')
            ->value('p.sessions_per_week');
    }

    /** @return object{enrollment_id: string, version_id: string, current_node_id: ?string, mastered_weight: int, total_weight: int, percent: int, pace: ?string, eta_weeks: ?string, last_report_at: ?string}|null */
    public function position(string $enrollmentId): ?object
    {
        return DB::table('enrollment_positions')->where('enrollment_id', $enrollmentId)->first();
    }
}
