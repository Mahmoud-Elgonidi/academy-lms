<?php

namespace Modules\Learning\Contracts\Events;

/** A student mastered a lesson of the curriculum (a step on the roadmap). */
final readonly class NodeMastered
{
    public function __construct(
        public string $academyId,
        public string $enrollmentId,
        public string $studentId,
        public string $nodeId,
    ) {}
}
