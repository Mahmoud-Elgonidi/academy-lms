<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Domain\NodeProgress;
use Modules\Learning\Domain\ProgressCalculator;
use Modules\Learning\Queries\ProgressQuery;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The progress projector (docs/architecture/04, "Projector"): replays every
 * handed-in lesson report of the enrollment, in the order the lessons were
 * taught, through the mastery rules, and rewrites enrollment_progress and
 * enrollment_positions. Replaying keeps it right when a report is edited or
 * the program changes. Returns the lessons mastered by this run.
 */
final class ProjectProgress
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<string> lesson ids newly mastered */
    public function handle(string $enrollmentId): array
    {
        return DB::transaction(function () use ($enrollmentId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["learning.progress:{$enrollmentId}"]);

            $before = DB::table('enrollment_progress')->where('enrollment_id', $enrollmentId)->whereNotNull('mastered_at')->pluck('node_id')->all();
            DB::table('enrollment_progress')->where('enrollment_id', $enrollmentId)->delete();

            $versionId = $this->enrollments->find($enrollmentId)?->curriculumVersionId;
            if ($versionId === null) {
                DB::table('enrollment_positions')->where('enrollment_id', $enrollmentId)->delete();

                return [];
            }

            $tree = $this->curriculum->tree($versionId);
            $items = DB::table('report_items as i')
                ->join('session_reports as r', fn ($j) => $j->on('r.academy_id', '=', 'i.academy_id')->on('r.id', '=', 'i.report_id'))
                ->where('r.enrollment_id', $enrollmentId)->where('r.kind', 'lesson')->whereIn('r.status', ['submitted', 'reviewed'])
                ->orderBy('r.session_starts_at')->orderBy('r.id')
                ->get(['r.id as report_id', 'r.session_starts_at', 'i.node_id', 'i.outcome', 'i.score']);

            $states = [];
            $gainedPerLesson = [];
            $lastAt = null;
            foreach ($items->groupBy('report_id') as $report) {
                $at = CarbonImmutable::parse($report->first()->session_starts_at);
                $gained = 0;
                foreach ($report as $item) {
                    $lesson = $tree->nodes[$item->node_id] ?? null;
                    if ($lesson === null || $lesson->kind !== 'lesson') {
                        continue;
                    }
                    $was = $states[$lesson->id] ?? new NodeProgress($lesson->id);
                    $states[$lesson->id] = MasteryRule::apply($was, $item->outcome, $item->score === null ? null : (int) $item->score, $lesson->masteryRule, $at);
                    $gained += ! $was->isMastered() && $states[$lesson->id]->isMastered() ? $lesson->weight : 0;
                }
                $gainedPerLesson[] = $gained;
                $lastAt = $at;
            }

            $academyId = $this->tenant->require()->id;
            $now = CarbonImmutable::now();
            DB::table('enrollment_progress')->insert(array_map(fn (NodeProgress $p) => [
                'academy_id' => $academyId,
                'enrollment_id' => $enrollmentId,
                'node_id' => $p->nodeId,
                'status' => $p->status,
                'times_seen' => $p->timesSeen,
                'high_scores' => $p->highScores,
                'last_score' => $p->lastScore,
                'last_outcome' => $p->lastOutcome,
                'first_seen_at' => $p->firstSeenAt,
                'mastered_at' => $p->masteredAt,
                'review_interval_days' => $p->reviewIntervalDays,
                'next_review_at' => $p->nextReviewAt,
                'updated_at' => $now,
            ], array_values($states)));

            $position = ProgressCalculator::position($tree, $states, $gainedPerLesson, $this->progress->lessonsPerWeek($enrollmentId));
            DB::table('enrollment_positions')->upsert([[
                'academy_id' => $academyId,
                'enrollment_id' => $enrollmentId,
                'version_id' => $versionId,
                'current_node_id' => $position->currentNodeId,
                'mastered_weight' => $position->masteredWeight,
                'total_weight' => $position->totalWeight,
                'percent' => $position->percent,
                'pace' => $position->pace,
                'eta_weeks' => $position->etaWeeks,
                'last_report_at' => $lastAt,
                'updated_at' => $now,
            ]], ['academy_id', 'enrollment_id'], ['version_id', 'current_node_id', 'mastered_weight', 'total_weight', 'percent', 'pace', 'eta_weeks', 'last_report_at', 'updated_at']);

            $mastered = array_keys(array_filter($states, fn (NodeProgress $p) => $p->isMastered()));

            return array_values(array_diff($mastered, $before));
        });
    }
}
