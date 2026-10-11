<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Platform\Games\GamePage;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameVersion;

/**
 * A live game's published page, by its version (games.play): what the
 * learner's screen opens in a sandboxed frame, or the app in its WebView.
 * Nothing in it is personal (the learner's items come after, from the
 * screen), so it needs no sign-in; a retired game's version is gone.
 */
final class GamePageController
{
    public function __invoke(string $version): Response
    {
        $row = GameVersion::query()->find($version);
        abort_if($row === null || Game::query()->whereKey($row->game_id)->value('status') !== 'live', 404);

        return GamePage::response($row->html, published: true);
    }
}
