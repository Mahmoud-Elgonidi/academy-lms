<?php

namespace Modules\Learning\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A practice round a learner finished in a game (Games tells Learning). */
final readonly class PracticeRound
{
    /** @param  array<string, array{answered: int, correct: int}>  $byNode  how it went on each lesson's items */
    public function __construct(
        public string $studentId,
        public ?string $enrollmentId,
        public ?string $homeworkId,
        public string $game,
        public string $gameTitle,
        public string $focus,
        public int $correct,
        public int $total,
        public int $stars,
        public int $seconds,
        public string $playedBy,
        public CarbonImmutable $playedAt,
        public array $byNode,
    ) {}
}
