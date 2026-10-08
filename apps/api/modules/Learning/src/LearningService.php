<?php

namespace Modules\Learning;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\DTOs\HomeworkLine;
use Modules\Learning\Contracts\DTOs\PlanLine;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Domain\PlannedItem;
use Modules\Learning\Domain\TodaysPlan;
use Modules\Learning\Materials\MaterialLinks;
use Modules\Learning\Models\Homework;
use Modules\Learning\Queries\ProgressQuery;

final class LearningService implements LearningApi
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
        private readonly MaterialLinks $materials,
    ) {}

    public function planFor(?string $enrollmentId, CarbonImmutable $lessonStart, bool $forTeacher = true): array
    {
        $versionId = $enrollmentId === null ? null : $this->enrollments->find($enrollmentId)?->curriculumVersionId;
        if ($versionId === null) {
            return [];
        }
        $tree = $this->curriculum->tree($versionId);
        $plan = TodaysPlan::for($tree, $this->progress->states($enrollmentId), $lessonStart);
        $materials = $this->materials->forLessons(array_map(fn (PlannedItem $i) => $i->nodeId, $plan), $forTeacher);

        return array_map(fn (PlannedItem $i) => new PlanLine(
            $i->nodeId,
            $tree->nodes[$i->nodeId]->title(),
            implode(' › ', array_map(fn ($a) => $a->title(), $tree->ancestorsOf($i->nodeId))),
            $i->reason,
            $tree->nodes[$i->nodeId]->objectives,
            $materials[$i->nodeId] ?? [],
        ), $plan);
    }

    public function lastHomework(string $studentId, ?string $enrollmentId, CarbonImmutable $lessonStart): ?HomeworkLine
    {
        $h = Homework::query()->withCount('submissions')->where('student_id', $studentId)
            ->when($enrollmentId !== null, fn ($q) => $q->where('enrollment_id', $enrollmentId))
            ->where('created_at', '<', $lessonStart)
            ->orderByDesc('created_at')->first();

        return $h === null ? null : new HomeworkLine($h->id, $h->title, $h->instructions, $h->status, $h->due_at, $h->grade, $h->feedback, (int) $h->submissions_count);
    }
}
