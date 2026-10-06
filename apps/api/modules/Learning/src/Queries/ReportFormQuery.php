<?php

namespace Modules\Learning\Queries;

use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Domain\PlannedItem;
use Modules\Learning\Domain\TodaysPlan;
use Modules\Learning\Models\SessionReport;

/**
 * What the report form starts from: "today's plan" for an open report, what
 * was handed in for a submitted one, and the lessons the teacher can add.
 */
final class ReportFormQuery
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
    ) {}

    public function tree(SessionReport $report): ?CurriculumTree
    {
        $versionId = $report->enrollment_id === null ? null : $this->enrollments->find($report->enrollment_id)?->curriculumVersionId;

        return $versionId === null ? null : $this->curriculum->tree($versionId);
    }

    /** @return list<array{node_id: string, title: string, path: string, reason: ?string, outcome: string, score: ?int, note: ?string, objectives: ?string}> */
    public function items(SessionReport $report): array
    {
        $tree = $this->tree($report);
        if ($tree === null) {
            return [];
        }
        $describe = fn (string $id) => [
            'title' => $tree->nodes[$id]->title(),
            'path' => implode(' › ', array_map(fn ($a) => $a->title(), $tree->ancestorsOf($id))),
            'objectives' => $tree->nodes[$id]->objectives,
        ];

        if (! $report->isOpen() || $report->items()->exists()) {
            return $report->items()->get()->filter(fn ($i) => $tree->has($i->node_id))->map(fn ($i) => [
                'node_id' => $i->node_id, 'reason' => null, 'outcome' => $i->outcome, 'score' => $i->score, 'note' => $i->note,
            ] + $describe($i->node_id))->values()->all();
        }

        $plan = TodaysPlan::for($tree, $this->progress->states((string) $report->enrollment_id), $report->session_starts_at);

        return array_map(fn (PlannedItem $i) => [
            'node_id' => $i->nodeId, 'reason' => $i->reason, 'outcome' => $i->suggestedOutcome, 'score' => null, 'note' => null,
        ] + $describe($i->nodeId), $plan);
    }

    /** @return array<string, string> lesson id => "Level › Unit › Lesson", in teaching order */
    public function lessonOptions(SessionReport $report): array
    {
        $tree = $this->tree($report);
        $options = [];
        foreach ($tree?->lessons() ?? [] as $lesson) {
            $options[$lesson->id] = implode(' › ', [...array_map(fn ($a) => $a->title(), $tree->ancestorsOf($lesson->id)), $lesson->title()]);
        }

        return $options;
    }
}
