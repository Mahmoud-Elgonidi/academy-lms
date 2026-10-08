<?php

namespace Modules\Learning\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** The last homework set before a lesson, as the classroom shows it beside the plan. */
final readonly class HomeworkLine
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $instructions,
        public string $status,          // assigned | submitted | reviewed | missed
        public CarbonImmutable $dueAt,
        public ?int $grade,             // 1–5, once marked
        public ?string $feedback,
        public int $submissions,
    ) {}
}
