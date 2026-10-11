<?php

namespace Modules\Learning\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * Something a learner should practise now, and why: homework that asks for
 * it, the last lesson, lessons due for review, or where the course stands.
 */
final readonly class PracticeFocus
{
    public const REASONS = ['homework', 'last_lesson', 'review', 'current'];

    /** @param  list<string>  $nodeIds  lessons, in teaching order */
    public function __construct(
        public string $reason,
        public ?string $enrollmentId,
        public array $nodeIds,
        public string $title,
        public ?string $homeworkId = null,
        public ?CarbonImmutable $dueAt = null,
        public bool $done = false,
    ) {}

    /** A stable key for this focus (a quest), for the screens and the next round. */
    public function key(): string
    {
        return $this->reason.':'.($this->homeworkId ?? $this->enrollmentId ?? '-');
    }
}
