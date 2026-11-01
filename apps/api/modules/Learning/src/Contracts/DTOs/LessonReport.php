<?php

namespace Modules\Learning\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A lesson report as another module reads it (the Quran's hifz map): which
 * lesson and student, when the lesson started, and each curriculum lesson's
 * outcome in it (introduced, practiced, mastered, needs_review).
 */
final readonly class LessonReport
{
    /** @param  array<string, string>  $outcomes  curriculum lesson id => outcome */
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $kind,
        public CarbonImmutable $sessionStartsAt,
        public array $outcomes,
    ) {}
}
