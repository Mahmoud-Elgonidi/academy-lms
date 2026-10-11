<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Games\PackageCheck;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameDraftTurn;

/**
 * The platform team opens a live (or retired) game to change it: a draft
 * that starts from the published page, with the story it was made from, so
 * the designer can take the next changes from there. Publishing it makes
 * the game's next version; the live one plays on meanwhile. One open draft
 * per game: opening again returns it.
 */
final class OpenGameDraft
{
    public function handle(Game $game, string $adminId): GameDraft
    {
        return DB::connection('pgsql_platform')->transaction(function () use ($game, $adminId) {
            $game = Game::query()->with('current')->lockForUpdate()->findOrFail($game->id);
            $open = GameDraft::query()->where('game_id', $game->id)->whereIn('status', GameDraft::OPEN)->latest('created_at')->first();
            if ($open !== null) {
                return $open;
            }
            $version = $game->current;
            $story = (string) (GameDraft::query()->where('game_id', $game->id)->whereNotNull('published_at')->latest('published_at')->value('story')
                ?? $game->summary_ar);

            $draft = GameDraft::query()->create([
                'game_id' => $game->id,
                'island_id' => $game->island_id,
                'stages' => $version?->manifest['stages'] ?? $game->stages,
                'kinds' => $version?->manifest['kinds'] ?? $game->kinds,
                'topic' => '',
                'story' => $story !== '' ? $story : $game->title_ar,
                'status' => 'ready',
                'title_ar' => $game->title_ar,
                'title_en' => $game->title_en,
                'summary_ar' => $game->summary_ar,
                'summary_en' => $game->summary_en,
                'manifest' => $version?->manifest,
                'html' => $version?->html,
                'issues' => $version ? PackageCheck::issues($version->html) : [],
                'created_by' => $adminId,
            ]);
            GameDraftTurn::query()->create(['draft_id' => $draft->id, 'role' => 'admin', 'body' => $draft->story, 'admin_id' => $adminId]);

            return $draft;
        });
    }
}
