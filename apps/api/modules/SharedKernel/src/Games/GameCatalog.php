<?php

namespace Modules\SharedKernel\Games;

/**
 * The platform's games, for the academies (Platform binds it; until then
 * there are none). Islands and their creatures make the world; a game is
 * live at one published version, which the learner's screen opens at
 * route('games.play', version) in a sandboxed frame and feeds the
 * learner's own practice items.
 */
interface GameCatalog
{
    /** @return list<CatalogIsland> in the world's order */
    public function islands(): array;

    /** @return list<CatalogGame> the live games, in the catalog's order */
    public function games(): array;

    /** A live game by its id or key. */
    public function game(string $idOrKey): ?CatalogGame;

    public function creature(string $key): ?CatalogCreature;
}
