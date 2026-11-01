<?php

namespace Modules\Scheduling\Contracts;

use Modules\Scheduling\Contracts\DTOs\StartCheck;

/**
 * Whether a family may start a course on its own for a learner and subject
 * now (docs/architecture/02, section 27, J3), with why not in the family's
 * words, and whether a teacher (the trial's) still takes them: the trial's
 * report opens the start flow from it.
 */
interface OwnCourseStart
{
    public function check(string $viewerId, string $learnerId, string $subjectId, ?string $teacherId = null): StartCheck;
}
