<?php

namespace Modules\Learning\Domain;

/** One line of "today's plan", which the report form starts from. */
final readonly class PlannedItem
{
    public function __construct(
        public string $nodeId,
        public string $reason,            // next | review
        public string $suggestedOutcome,  // introduced | practiced
    ) {}
}
