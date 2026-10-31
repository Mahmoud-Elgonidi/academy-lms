<?php

namespace Modules\Learning\Contracts;

use Illuminate\Contracts\Support\Htmlable;

/** Reports and homework without the Quran: nothing to recite, check or show. */
final class NoLearningQuran implements LearningQuran
{
    public function recitationOffered(): bool
    {
        return false;
    }

    public function recitationTarget(mixed $input, string $accepts, ?array $current): ?array
    {
        // Nothing new is asked to recite; taking it off stays possible.
        return $input === null || $input === [] ? null : $current;
    }

    public function presentTarget(?array $expected): ?array
    {
        return null;
    }

    public function targetFromLessons(array $nodeIds): ?array
    {
        return null;
    }

    public function targetFromMistakes(string $sessionId): ?array
    {
        return null;
    }

    public function checks(string $homeworkId, ?array $expected): array
    {
        return [];
    }

    public function reportFields(?array $fromMistakes, ?string $suggestion): array
    {
        return [];
    }

    public function targetView(?array $expected): ?Htmlable
    {
        return null;
    }

    public function checkView(?array $check): ?Htmlable
    {
        return null;
    }

    public function studentActions(string $enrollmentId): array
    {
        return [];
    }
}
