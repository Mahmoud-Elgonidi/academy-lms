<?php

namespace Modules\Learning\Contracts\DTOs;

/**
 * The games on a learner's journey (JourneyGames): plain arrays the journey's
 * answer carries as they are, instants as CarbonImmutable.
 */
final readonly class JourneyGamesLayer
{
    /**
     * @param  array<string, mixed>  $allowance  may, reason (off, paused, night, limit), limit, used, left, night_lock
     * @param  array<string, list<array<string, mixed>>>  $games  by lesson: the games that can play its items, best first
     * @param  array<string, array<string, mixed>>  $stats  by lesson: rounds, best_seconds, last_seconds, best_stars
     * @param  array<string, array<string, mixed>>  $islands  by island key: creatures (found or not), the next to hatch
     * @param  array{have: int, need: int, ready: bool}  $egg
     * @param  ?array<string, mixed>  $buddy
     * @param  list<array<string, mixed>>  $recent  the last rounds
     * @param  ?array<string, mixed>  $limits  a guardian's limits for the child
     * @param  ?array<string, mixed>  $glance  a guardian's look at the child's week
     */
    public function __construct(
        public array $allowance,
        public array $games,
        public array $stats,
        public array $islands,
        public array $egg,
        public ?array $buddy,
        public int $found,
        public int $total,
        public array $recent,
        public ?array $limits = null,
        public ?array $glance = null,
    ) {}
}
