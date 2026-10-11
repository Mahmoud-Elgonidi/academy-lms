<?php

namespace Modules\Learning\Queries\Journey;

use Modules\Learning\Contracts\DTOs\JourneyGamesLayer;
use Modules\Learning\Contracts\JourneyGames;

/** No games module: the journey without games. */
final class NoJourneyGames implements JourneyGames
{
    public function layer(string $studentId, string $stage, array $stations, int $stars, bool $guardian): ?JourneyGamesLayer
    {
        return null;
    }
}
