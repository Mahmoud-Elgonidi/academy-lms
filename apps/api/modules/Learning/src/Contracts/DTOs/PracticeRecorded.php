<?php

namespace Modules\Learning\Contracts\DTOs;

/** What recording a round changed: whether it handed homework in, and its title. */
final readonly class PracticeRecorded
{
    public function __construct(
        public string $roundId,
        public bool $handedIn = false,
        public ?string $homeworkTitle = null,
    ) {}
}
