<?php

namespace Modules\Games\Queries;

use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\CatalogIsland;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A learner's games world, as every screen shows it (the family panel, the
 * app, the portal): the islands with the creatures they hatched and the
 * games they can play now, their quests (what to practise, with the game
 * for it), their stars and the next egg, and today's minutes.
 */
final class GamesWorld
{
    public function __construct(
        private readonly GameCatalog $catalog,
        private readonly PracticePool $pool,
        private readonly PlayAllowance $allowance,
        private readonly PeopleApi $people,
    ) {}

    /** @return array<string, mixed> */
    public function for(string $studentId): array
    {
        $stage = $this->people->studentProfile($studentId)->stage->value;
        $games = $this->pool->games($stage);
        $focus = $this->pool->focus($studentId);
        $quests = $this->pool->quests($studentId, $focus, $games);
        $all = $this->pool->all($focus);
        $finds = CreatureFind::query()->where('student_id', $studentId)->get()->keyBy('creature_key');
        $stars = (int) GamePlay::query()->where('student_id', $studentId)->whereNotNull('finished_at')->sum('stars');
        $titles = [];
        foreach ($this->catalog->games() as $g) {
            $titles[$g->id] = $g->title();
        }

        return [
            'stage' => $stage,
            'allowance' => $this->allowance->for($studentId),
            'stars' => $stars,
            'egg' => ['have' => $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'found' => $finds->count(),
            'quests' => array_map(fn (array $q) => [
                'key' => $q['focus']->key(),
                'reason' => $q['focus']->reason,
                'title' => $q['focus']->title,
                'homework_id' => $q['focus']->homeworkId,
                'due_at' => $q['focus']->dueAt,
                'done' => $q['focus']->done,
                'items' => $q['items'],
                'game' => $this->game($q['game'], true),
            ], $quests),
            'islands' => array_map(fn (CatalogIsland $island) => [
                'key' => $island->key,
                'name' => $island->name(),
                'story' => $island->story(),
                'emoji' => $island->emoji,
                'colors' => $island->colors,
                'music' => $island->music,
                'creatures' => array_map(fn (CatalogCreature $c) => [
                    'key' => $c->key,
                    'name' => $c->name(),
                    'emoji' => $c->emoji,
                    'rarity' => $c->rarity,
                    'found' => $finds->has($c->key),
                    'found_at' => $finds->get($c->key)?->found_at,
                ], $island->creatures),
                'games' => array_values(array_map(fn (CatalogGame $g) => $this->game($g, Round::pick($g, $all) !== null),
                    array_filter($games, fn (CatalogGame $g) => $g->islandKey === $island->key))),
            ], $this->catalog->islands()),
            'recent' => GamePlay::query()->where('student_id', $studentId)->whereNotNull('finished_at')->latest('finished_at')->limit(5)->get()
                ->map(fn (GamePlay $p) => ['game' => $titles[$p->game_id] ?? $p->game_key, 'stars' => (int) $p->stars, 'correct' => (int) $p->correct, 'total' => $p->total, 'at' => $p->finished_at, 'focus' => $p->focus])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function game(CatalogGame $g, bool $playable): array
    {
        return [
            'id' => $g->id,
            'key' => $g->key,
            'island' => $g->islandKey,
            'title' => $g->title(),
            'summary' => $g->summary(),
            'how_to_play' => $g->howToPlay(),
            'kinds' => array_map(fn ($k) => $k->value, $g->kinds),
            'playable' => $playable,
        ];
    }

    /** The reason a quest is shown, for its label (games::games.reasons). */
    public static function reasons(): array
    {
        return [...PracticeFocus::REASONS, 'free'];
    }
}
