<?php

namespace Modules\Learning\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumNode;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\DTOs\MaterialLine;
use Modules\Learning\Domain\NodeProgress;
use Modules\Learning\Domain\ProgressCalculator;
use Modules\Learning\Materials\MaterialLinks;

/**
 * The roadmap screen (docs/architecture/02, section 6, step 5), the same for
 * the student, the guardian, the teacher and the back office: levels, units
 * and lessons marked done ✓, here ●, due for review ↻ or to come ○, with the
 * % per level, the pace and the expected finish, and each lesson's materials
 * when asked for (the family's, or the teachers' too).
 */
final class RoadmapQuery
{
    /** The lessons' materials: none (a summary), the family's, or the teachers' too (teachers and staff). */
    public const MATERIALS_NONE = null;

    public const MATERIALS_FAMILY = 'family';

    public const MATERIALS_TEACHERS = 'teachers';

    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
        private readonly MaterialLinks $materials,
    ) {}

    /** @return array<string, mixed>|null null when the enrollment follows no program */
    /** @var array<string, object> this roadmap's practice, by lesson */
    private array $practice = [];

    public function for(string $enrollmentId, ?string $materials = self::MATERIALS_NONE): ?array
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
        $files = $materials === self::MATERIALS_NONE ? [] : $this->materials->forLessons(array_map(fn ($n) => $n->id, $tree->lessons()), $materials === self::MATERIALS_TEACHERS);
        $this->practice = DB::table('practice_stats')->where('enrollment_id', $enrollmentId)->get()->keyBy('node_id')->all();

        $levels = [];
        foreach ($tree->childrenOf(null) as $top) {
            $units = [];
            $direct = $top->kind === 'lesson' ? [$top] : [];
            foreach ($tree->childrenOf($top->id) as $child) {
                if ($child->kind === 'lesson') {
                    $direct[] = $child;
                } else {
                    $units[] = ['id' => $child->id, 'title' => $child->title(), 'percent' => ProgressCalculator::percentUnder($tree, $states, $child->id),
                        'lessons' => $this->lessons($tree->lessons($child->id), $states, $current, $files)];
                }
            }
            $levels[] = [
                'id' => $top->id,
                'kind' => $top->kind,
                'title' => $top->title(),
                'percent' => ProgressCalculator::percentUnder($tree, $states, $top->id),
                'has_current' => $current !== null && ($current === $top->id || in_array($top->id, array_map(fn ($a) => $a->id, $tree->ancestorsOf($current)), true)),
                'lessons' => $this->lessons($direct, $states, $current, $files),
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

    /**
     * The lines of a level's or a unit's lessons.
     *
     * @param  list<CurriculumNode>  $nodes
     * @param  array<string, NodeProgress>  $states
     * @param  array<string, list<MaterialLine>>  $files
     */
    private function lessons(array $nodes, array $states, ?string $current, array $files): array
    {
        $lines = [];
        foreach ($nodes as $n) {
            $lines[] = $this->lesson($n, $states[$n->id] ?? null, $current, $files[$n->id] ?? []);
        }

        return $lines;
    }

    /** @param  list<MaterialLine>  $materials */
    private function lesson(CurriculumNode $n, ?NodeProgress $p, ?string $current, array $materials): array
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
            // Files and links on the lesson (a file's url is signed for twelve hours); empty when not asked for.
            'materials' => array_map(fn (MaterialLine $m) => $m->toArray(), $materials),
            // How practice in the games went on this lesson's items (null: not practised yet).
            'practice' => ($s = $this->practice[$n->id] ?? null) === null ? null : [
                'rounds' => (int) $s->rounds,
                'answered' => (int) $s->answered,
                'correct' => (int) $s->correct,
                'percent' => $s->answered > 0 ? (int) round(100 * $s->correct / $s->answered) : null,
            ],
        ];
    }

    /** @return array{id: string, title: string, path: list<string>} */
    private function here(CurriculumTree $tree, string $id): array
    {
        return ['id' => $id, 'title' => $tree->nodes[$id]->title(), 'path' => array_map(fn ($a) => $a->title(), $tree->ancestorsOf($id))];
    }
}
