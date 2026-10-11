<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\Validator;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameIsland;

/**
 * The platform team edits what the catalog says about a game (the approved
 * design, Studio-Game): its names, what the child reads about it, its island
 * and the looks it is offered to. The page itself (a published version) never
 * changes here; developing it is a new version through a draft.
 */
final class SaveGameDetails
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array{title_ar: string, title_en: string, summary_ar: string, summary_en: string, island_id: string, stages: list<string>}  $data */
    public function handle(Game $game, array $data): Game
    {
        $data = Validator::make($data, [
            'title_ar' => ['required', 'string', 'max:80'],
            'title_en' => ['required', 'string', 'max:80'],
            'summary_ar' => ['required', 'string', 'max:300'],
            'summary_en' => ['required', 'string', 'max:300'],
            'island_id' => ['required', 'string', 'exists:pgsql_platform.game_islands,id'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => ['in:'.implode(',', StartGameDraft::STAGES)],
        ], [], trans('platform::games.attributes'))->validate();

        $game->forceFill([
            'title_ar' => trim($data['title_ar']), 'title_en' => trim($data['title_en']),
            'summary_ar' => trim($data['summary_ar']), 'summary_en' => trim($data['summary_en']),
            'island_id' => GameIsland::query()->findOrFail($data['island_id'])->id,
            'stages' => array_values(array_unique($data['stages'])),
        ])->save();
        $this->audit->record(null, 'platform.game_details_saved', 'game', $game->id, ['game' => $game->title_ar.' / '.$game->title_en]);

        return $game;
    }
}
