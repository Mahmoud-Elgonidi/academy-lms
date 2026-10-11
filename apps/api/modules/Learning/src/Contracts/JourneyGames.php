<?php

namespace Modules\Learning\Contracts;

use Modules\Learning\Contracts\DTOs\JourneyGamesLayer;
use Modules\Learning\Contracts\DTOs\JourneyStation;

/**
 * The games on a learner's journey (docs/architecture/02, section 18), which
 * the Games module binds; the journey works the same without it (no games,
 * no creatures): the islands, the lessons, the missions and the stars stay.
 */
interface JourneyGames
{
    /**
     * For each lesson the learner may practise now, the games that can play its items; the creatures on the
     * journey's islands, the egg the stars are filling and today's play. Null when there are no games for this
     * learner (the academy turned them off, or none fits their look).
     *
     * @param  list<JourneyStation>  $stations
     * @param  bool  $guardian  a guardian looks (adds the limits they set and their glance at the week)
     */
    public function layer(string $studentId, string $stage, array $stations, int $stars, bool $guardian): ?JourneyGamesLayer;
}
