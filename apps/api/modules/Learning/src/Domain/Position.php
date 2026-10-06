<?php

namespace Modules\Learning\Domain;

/** The student on the roadmap: "you are here", how far, how fast, how long to go. */
final readonly class Position
{
    public function __construct(
        public ?string $currentNodeId,
        public int $masteredWeight,
        public int $totalWeight,
        public int $percent,
        public ?float $pace,
        public ?float $etaWeeks,
    ) {}

    public function finished(): bool
    {
        return $this->totalWeight > 0 && $this->masteredWeight >= $this->totalWeight;
    }
}
