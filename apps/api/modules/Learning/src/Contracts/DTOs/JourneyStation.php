<?php

namespace Modules\Learning\Contracts\DTOs;

/** A lesson on the journey the learner may practise now, with the look of its island and the game a teacher chose for it. */
final readonly class JourneyStation
{
    public function __construct(
        public string $id,
        public string $enrollmentId,
        /** The world island its level is drawn as (GameCatalog), null without one. */
        public ?string $island,
        /** done, review, here (a lesson still ahead is not practised) */
        public string $state,
        /** The catalog game a teacher chose for homework on it. */
        public ?string $game = null,
    ) {}
}
