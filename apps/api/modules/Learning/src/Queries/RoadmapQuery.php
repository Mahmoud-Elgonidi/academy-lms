<?php

namespace Modules\Learning\Queries;

use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Domain\NodeProgress;
use Modules\Learning\Domain\ProgressCalculator;

/**
 * The roadmap screen (docs/architecture/02, section 6, step 5), the same for
 * the student, the guardian, the teacher and the back office: levels, units
 * and lessons marked done ✓, here ●, due for review ↻ or to come ○, with the
 * % per level, the pace and the expected finish.
 */
final class RoadmapQuery
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
    ) {}

    /** @return array<string, mixed>|null null when the enrollment follows no program */
    public function for(string $enrollmentId): ?array
    {
        $enrollment = $this->enrollments->find($enrollmentId);
        if ($enrollment?->curriculumVersionId === null) {
            return null;
        }

        $tree = $this->curriculum->tree($enrollment->curriculumVersionId);
        $states = $this->progress->states($enrollmentId);
        $position = $this->progress->position($enrollmentId);
        $calculated = ProgressCalculator::position($tree, $states, [], $this->progress->lessonsPerWeek($enrollmentId));
        $current = $position?->current_node_id ?? $calculated->currentNodeId;
        $lesson = fn (CurriculumNode $n) => $this->lesson($n, $states[$n->id] ?? null, $current);

        $levels = [];
        foreach ($tree->childrenOf(null) as $top) {
            $units = [];
            $direct = [];
            foreach ($tree->childrenOf($top->id) as $child) {
                if ($child->kind === 'lesson') {
                    $direct[] = $lesson($child);
                } else {
                    $units[] = ['id' => $child->id, 'title' => $child->title(), 'percent' => ProgressCalculator::percentUnder($tree, $states, $child->id),
                        'lessons' => array_map($lesson, $tree->lessons($child->id))];
                }
            }
            $levels[] = [
                'id' => $top->id,
                'kind' => $top->kind,
                'title' => $top->title(),
                'percent' => ProgressCalculator::percentUnder($tree, $states, $top->id),
                'has_current' => $current !== null && ($current === $top->id || in_array($top->id, array_map(fn ($a) => $a->id, $tree->ancestorsOf($current)), true)),
                'lessons' => $top->kind === 'lesson' ? [$lesson($top)] : $direct,
                'units' => $units,
            ];
        }

        $program = $enrollment->programId ? $this->curriculum->program($enrollment->programId) : null;

        return [
            'program' => ['id' => $enrollment->programId, 'name' => $program?->name],
            'version_id' => $enrollment->curriculumVersionId,
            'percent' => (int) ($position->percent ?? 0),
            'mastered_weight' => (int) ($position->mastered_weight ?? 0),
            'total_weight' => $tree->totalWeight(),
            'lessons' => ['mastered' => count(array_filter($tree->lessons(), fn ($n) => isset($states[$n->id]) && $states[$n->id]->isMastered())), 'total' => count($tree->lessons())],
            'pace' => $position?->pace === null ? null : (float) $position->pace,
            'eta_weeks' => $position?->eta_weeks === null ? null : (float) $position->eta_weeks,
            'lessons_per_week' => $this->progress->lessonsPerWeek($enrollmentId),
            'current' => $current === null ? null : $this->here($tree, $current),
            'finished' => $current === null && $tree->lessons() !== [],
            'levels' => $levels,
        ];
    }

    /** @return array<string, mixed> */
    private function lesson(CurriculumNode $n, ?NodeProgress $p, ?string $current): array
    {
        $state = match (true) {
            $p?->status === 'needs_review' => 'review',
            $p?->isMastered() ?? false => 'done',
            $n->id === $current => 'here',
            default => 'todo',
        };

        return [
            'id' => $n->id,
            'title' => $n->title(),
            'state' => $state,
            'times_seen' => $p->timesSeen ?? 0,
            'last_outcome' => $p?->lastOutcome,
            'last_score' => $p?->lastScore,
            'est_sessions' => $n->estSessions,
            'weight' => $n->weight,
        ];
    }

    /** @return array{id: string, title: string, path: list<string>} */
    private function here(CurriculumTree $tree, string $id): array
    {
        return ['id' => $id, 'title' => $tree->nodes[$id]->title(), 'path' => array_map(fn ($a) => $a->title(), $tree->ancestorsOf($id))];
    }
}
