<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Games\Game;

/**
 * The platform team takes a game off the catalog (retired: no academy
 * offers it from now on; what learners played stays theirs) or brings it
 * back at its published version.
 */
final class SetGameStatus
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(Game $game, string $status): Game
    {
        if (! in_array($status, ['live', 'retired'], true) || ($status === 'live' && $game->current_version_id === null)) {
            throw ValidationException::withMessages(['status' => __('platform::games.errors.no_version')]);
        }
        $game->forceFill(['status' => $status])->save();
        $this->audit->record(null, 'platform.game_'.($status === 'live' ? 'revived' : 'retired'), 'game', $game->id, ['game' => $game->title_ar.' / '.$game->title_en]);

        return $game;
    }
}
