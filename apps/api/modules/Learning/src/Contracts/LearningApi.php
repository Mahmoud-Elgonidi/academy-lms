<?php

namespace Modules\Learning\Contracts;

use Carbon\CarbonImmutable;
use Modules\Learning\Contracts\DTOs\PlanLine;

/** What other modules may ask of Learning. */
interface LearningApi
{
    /** @return list<PlanLine> today's plan for a lesson of this enrollment; empty when it follows no program */
    public function planFor(?string $enrollmentId, CarbonImmutable $lessonStart): array;
}
