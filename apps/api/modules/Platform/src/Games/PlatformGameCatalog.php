<?php

namespace Modules\Platform\Games;

use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameCreature;
use Modules\Platform\Models\Games\GameIsland;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\CatalogIsland;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Games\ItemKind;

/**
 * The catalog the academies see: the islands with their creatures and the
 * live games at their published version, read on the platform connection
 * once per request (scoped).
 */
final class PlatformGameCatalog implements GameCatalog
{
    /** @var list<CatalogIsland>|null */
    private ?array $islands = null;

    /** @var list<CatalogGame>|null */
    private ?array $games = null;

    public function islands(): array
    {
        return $this->islands ??= GameIsland::query()->with('creatures')->orderBy('sort')->orderBy('key')->get()
            ->map(fn (GameIsland $island) => new CatalogIsland(
                key: $island->key,
                nameAr: $island->name_ar,
                nameEn: $island->name_en,
                storyAr: $island->story_ar,
                storyEn: $island->story_en,
                emoji: $island->emoji,
                colors: $island->colors,
                music: $island->music,
                sort: $island->sort,
                creatures: $island->creatures->map(fn (GameCreature $c) => self::creatureOf($c, $island->key))->all(),
            ))->all();
    }

    public function games(): array
    {
        return $this->games ??= Game::query()->with(['island', 'current'])->where('status', 'live')
            ->orderBy('sort')->orderBy('key')->get()
            ->map(function (Game $game): CatalogGame {
                $version = $game->current;
                $manifest = $version?->manifest ?? [];

                return new CatalogGame(
                    id: $game->id,
                    key: $game->key,
                    islandKey: $game->island->key,
                    versionId: (string) $version?->id,
                    version: (int) $version?->version,
                    titleAr: $game->title_ar,
                    titleEn: $game->title_en,
                    summaryAr: $game->summary_ar,
                    summaryEn: $game->summary_en,
                    howToPlayAr: (string) ($manifest['how_to_play_ar'] ?? ''),
                    howToPlayEn: (string) ($manifest['how_to_play_en'] ?? ''),
                    stages: array_values($manifest['stages'] ?? $game->stages),
                    kinds: array_values(array_filter(array_map(fn ($k) => ItemKind::tryFrom((string) $k), $manifest['kinds'] ?? $game->kinds))),
                    minItems: max(1, (int) ($manifest['min_items'] ?? 1)),
                    maxItems: max(1, (int) ($manifest['max_items'] ?? 10)),
                    sort: $game->sort,
                );
            })->all();
    }

    public function game(string $idOrKey): ?CatalogGame
    {
        foreach ($this->games() as $game) {
            if ($game->id === $idOrKey || $game->key === strtolower($idOrKey)) {
                return $game;
            }
        }

        return null;
    }

    public function creature(string $key): ?CatalogCreature
    {
        foreach ($this->islands() as $island) {
            foreach ($island->creatures as $creature) {
                if ($creature->key === $key) {
                    return $creature;
                }
            }
        }

        return null;
    }

    private static function creatureOf(GameCreature $c, string $islandKey): CatalogCreature
    {
        return new CatalogCreature($c->key, $islandKey, $c->name_ar, $c->name_en, $c->emoji, $c->rarity, $c->sort);
    }
}
