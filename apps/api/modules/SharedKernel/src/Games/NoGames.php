<?php

namespace Modules\SharedKernel\Games;

/** No islands and no games: until Platform binds its catalog. */
final class NoGames implements GameCatalog
{
    public function islands(): array
    {
        return [];
    }

    public function games(): array
    {
        return [];
    }

    public function game(string $idOrKey): ?CatalogGame
    {
        return null;
    }

    public function creature(string $key): ?CatalogCreature
    {
        return null;
    }
}
