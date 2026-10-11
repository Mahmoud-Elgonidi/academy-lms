<?php

namespace Modules\Platform\Games\Designer;

/**
 * Makes a game from the platform team's story (and remakes it with each
 * change they ask for): the page, its titles and what it plays. Bound to
 * ClaudeGameDesigner; tests bind their own.
 *
 * @throws DesignFailed
 */
interface GameDesigner
{
    public function design(DesignBrief $brief): DesignedGame;
}
