<?php

namespace Modules\Learning\Contracts\DTOs;

/** One line of "today's plan", as the classroom shows it beside the video. */
final readonly class PlanLine
{
    public function __construct(
        public string $lessonId,
        public string $title,
        public string $path,
        public string $reason,          // next | review
        public ?string $objectives,
    ) {}
}
