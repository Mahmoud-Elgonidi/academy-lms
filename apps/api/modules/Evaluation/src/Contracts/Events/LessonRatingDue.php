<?php

namespace Modules\Evaluation\Contracts\Events;

/** Time to ask the family how a finished lesson went (at most once a week for each student). */
final readonly class LessonRatingDue
{
    public function __construct(public string $academyId, public string $sessionId, public string $studentId) {}
}
