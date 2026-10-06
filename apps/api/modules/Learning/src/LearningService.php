<?php

namespace Modules\Learning;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\DTOs\PlanLine;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Domain\PlannedItem;
use Modules\Learning\Domain\TodaysPlan;
use Modules\Learning\Queries\ProgressQuery;

final class LearningService implements LearningApi
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProgressQuery $progress,
    ) {}

    public function planFor(?string $enrollmentId, CarbonImmutable $lessonStart): array
    {
        $versionId = $enrollmentId === null ? null : $this->enrollments->find($enrollmentId)?->curriculumVersionId;
        if ($versionId === null) {
            return [];
        }
        $tree = $this->curriculum->tree($versionId);

        return array_map(fn (PlannedItem $i) => new PlanLine(
            $i->nodeId,
            $tree->nodes[$i->nodeId]->title(),
            implode(' › ', array_map(fn ($a) => $a->title(), $tree->ancestorsOf($i->nodeId))),
            $i->reason,
            $tree->nodes[$i->nodeId]->objectives,
        ), TodaysPlan::for($tree, $this->progress->states($enrollmentId), $lessonStart));
    }
}
