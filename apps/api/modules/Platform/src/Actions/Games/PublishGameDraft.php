<?php

namespace Modules\Platform\Actions\Games;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Games\PackageCheck;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameVersion;

/**
 * The platform team publishes a draft it has played: a new game (with the
 * key it chooses) or the next version of the game the draft was opened
 * from. The page must pass PackageCheck; the version is written once and
 * never changes, and the game goes live at it for every academy.
 */
final class PublishGameDraft
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(GameDraft $draft, string $adminId, ?string $key = null): GameVersion
    {
        return DB::connection('pgsql_platform')->transaction(function () use ($draft, $adminId, $key) {
            $draft = GameDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ($draft->status !== 'ready' || $draft->html === null || $draft->manifest === null) {
                throw ValidationException::withMessages(['draft' => __('platform::games.errors.not_ready')]);
            }
            if (! PackageCheck::passes($draft->html)) {
                throw ValidationException::withMessages(['draft' => __('platform::games.errors.issues')]);
            }

            $game = $draft->game_id ? Game::query()->lockForUpdate()->findOrFail($draft->game_id) : null;
            if ($game === null) {
                $key = strtolower(trim((string) ($key ?: Str::slug((string) $draft->title_en))));
                Validator::make(['key' => $key], ['key' => ['required', 'regex:/^[a-z][a-z0-9-]{1,40}$/', Rule::unique('pgsql_platform.games', 'key')]],
                    [], ['key' => __('platform::games.attributes.key')])->validate();
                $game = new Game(['key' => $key, 'status' => 'draft', 'created_by' => $adminId, 'sort' => (int) Game::query()->max('sort') + 1]);
            }

            $manifest = $draft->manifest;
            $game->fill([
                'island_id' => $draft->island_id,
                'title_ar' => (string) $draft->title_ar,
                'title_en' => (string) $draft->title_en,
                'summary_ar' => (string) $draft->summary_ar,
                'summary_en' => (string) $draft->summary_en,
                'stages' => $manifest['stages'],
                'kinds' => $manifest['kinds'],
            ])->save();

            $version = GameVersion::query()->create([
                'game_id' => $game->id,
                'version' => (int) GameVersion::query()->where('game_id', $game->id)->max('version') + 1,
                'html' => $draft->html,
                'sha256' => hash('sha256', $draft->html),
                'manifest' => $manifest,
                'draft_id' => $draft->id,
                'published_by' => $adminId,
                'published_at' => now(),
            ]);
            $game->forceFill(['current_version_id' => $version->id, 'status' => 'live'])->save();
            $draft->forceFill(['status' => 'published', 'game_id' => $game->id, 'published_at' => now(), 'published_version_id' => $version->id])->save();

            $this->audit->record(null, 'platform.game_published', 'game', $game->id, ['game' => $game->title_ar.' / '.$game->title_en, 'version' => $version->version]);

            return $version;
        });
    }
}
