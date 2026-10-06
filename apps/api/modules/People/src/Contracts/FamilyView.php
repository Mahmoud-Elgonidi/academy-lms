<?php

namespace Modules\People\Contracts;

use Modules\People\Contracts\DTOs\Learner;

/**
 * Whose account a student or guardian is looking at on the web. A student
 * sees their own; a guardian, each child they are linked to, one at a
 * time (the choice holds for their browser session).
 */
interface FamilyView
{
    /** @return list<Learner> the viewer themselves when a student, then their children */
    public function learners(string $viewerId): array;

    /** The learner chosen now, or the first one; null when the viewer follows nobody. */
    public function current(string $viewerId): ?Learner;

    /** Chooses one of the viewer's learners; anyone else is ignored. */
    public function choose(string $viewerId, string $learnerId): void;

    public function follows(string $viewerId, string $learnerId): bool;

    /** The look of the viewer's area: their own stage when they are a student; the classic look for a guardian. */
    public function stage(string $viewerId): LearnerStage;
}
