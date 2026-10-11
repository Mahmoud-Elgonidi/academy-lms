<?php

namespace Modules\Games\Queries;

use Illuminate\Support\Collection;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\LearningApi;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\CatalogIsland;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A learner's games world, as every screen shows it (the family panel, the
 * app, the portal): the islands with the creatures they hatched and the
 * games they can play now, their quests (what to practise, with the game
 * for it), their stars and the next egg, and today's minutes. An island is
 * open when it has a game for the learner's look; each of its games says
 * which quest it plays, how often it was played and its best stars.
 */
final class GamesWorld
{
    public function __construct(
        private readonly GameCatalog $catalog,
        private readonly PracticePool $pool,
        private readonly PlayAllowance $allowance,
        private readonly PeopleApi $people,
        private readonly LearningApi $learning,
    ) {}

    /** @return array<string, mixed> */
    public function for(string $studentId): array
    {
        $stage = $this->people->studentProfile($studentId)->stage->value;
        $games = $this->pool->games($stage);
        $focus = $this->pool->focus($studentId);
        $finished = GamePlay::query()->where('student_id', $studentId)->whereNotNull('finished_at');
        $past = (clone $finished)->latest('finished_at')->limit(200)->get(['game_id', 'items', 'seconds', 'correct', 'total', 'stars', 'finished_at']);
        $quests = array_map(fn (array $q) => $this->timed([
            'key' => $q['focus']->key(),
            'reason' => $q['focus']->reason,
            'title' => $q['focus']->title,
            'homework_id' => $q['focus']->homeworkId,
            'due_at' => $q['focus']->dueAt,
            'done' => $q['focus']->done,
            'items' => $q['items'],
            'game' => $this->game($q['game'], true),
        ], $q['focus']->nodeIds, $past), $this->pool->quests($studentId, $focus, $games));
        $all = $this->pool->all($focus);
        /** @var Collection<string, CreatureFind> $finds */
        $finds = CreatureFind::query()->where('student_id', $studentId)->get()->keyBy('creature_key');
        $stars = (int) (clone $finished)->sum('stars');
        $journeyStars = $this->learning->stars($studentId);
        $byGame = (clone $finished)->selectRaw('game_id, count(*) as plays, max(stars) as best')->groupBy('game_id')->get()->keyBy('game_id');
        $titles = [];
        foreach ($this->catalog->games() as $g) {
            $titles[$g->id] = $g->title();
        }
        $buddy = $finds->firstWhere('buddy', true) ?? $finds->sortByDesc('found_at')->first();

        return [
            'stage' => $stage,
            'allowance' => $this->allowance->for($studentId),
            'stars' => $stars,
            // Eggs fill from all of the journey's stars (lessons, homework and chests too).
            'egg' => ['have' => $journeyStars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'found' => $finds->count(),
            'total' => array_sum(array_map(fn (CatalogIsland $i) => count($i->creatures), $this->catalog->islands())),
            'buddy' => $buddy === null ? null : $this->creatureOf($buddy->creature_key, $finds),
            'quests' => $quests,
            'islands' => array_map(function (CatalogIsland $island) use ($games, $all, $quests, $finds, $byGame) {
                $mine = array_values(array_filter($games, fn (CatalogGame $g) => $g->islandKey === $island->key));
                $list = array_map(function (CatalogGame $g) use ($all, $quests, $byGame) {
                    $quest = collect($quests)->first(fn (array $q) => $q['game']['id'] === $g->id);

                    return $this->game($g, Round::pick($g, $all) !== null) + [
                        'plays' => (int) ($byGame->get($g->id)?->plays ?? 0),
                        'best' => (int) ($byGame->get($g->id)?->best ?? 0),
                        'quest' => $quest === null ? null : ['key' => $quest['key'], 'reason' => $quest['reason'], 'title' => $quest['title'], 'items' => $quest['items'], 'done' => $quest['done']],
                    ];
                }, $mine);
                $next = collect($island->creatures)->first(fn (CatalogCreature $c) => ! $finds->has($c->key));

                return [
                    'key' => $island->key,
                    'name' => $island->name(),
                    'story' => $island->story(),
                    'emoji' => $island->emoji,
                    'colors' => $island->colors,
                    'music' => $island->music,
                    'open' => $list !== [],
                    'fresh' => collect($list)->contains(fn (array $g) => $g['playable'] && $g['plays'] === 0),
                    'homework' => collect($list)->contains(fn (array $g) => ($g['quest']['reason'] ?? null) === 'homework'),
                    'creatures' => array_map(fn (CatalogCreature $c) => $this->creature($c, $finds), $island->creatures),
                    'next' => $next === null ? null : ['key' => $next->key, 'look' => $next->look, 'rarity' => $next->rarity],
                    'games' => $list,
                ];
            }, $this->catalog->islands()),
            /** Days in a row (to today, on the learner's clock) with a finished round. */
            'streak' => $this->streak($past, $this->allowance->zone($studentId)),
            /** The last rounds, oldest first: how much was right first time (0 to 1) and the stars. */
            'history' => $past->take(10)->reverse()->values()->map(fn (GamePlay $p) => ['share' => $p->total > 0 ? round((int) $p->correct / $p->total, 2) : 0.0, 'stars' => (int) $p->stars, 'at' => $p->finished_at])->all(),
            'recent' => (clone $finished)->latest('finished_at')->limit(5)->get()
                ->map(fn (GamePlay $p) => ['game' => $titles[$p->game_id] ?? $p->game_key, 'stars' => (int) $p->stars, 'correct' => (int) $p->correct, 'total' => $p->total, 'at' => $p->finished_at, 'focus' => $p->focus])->all(),
        ];
    }

    /**
     * A quest's race against itself: the rounds played on its lessons only, their best and last time.
     *
     * @param  array<string, mixed>  $quest
     * @param  list<string>  $nodeIds
     * @return array<string, mixed>
     */
    private function timed(array $quest, array $nodeIds, Collection $past): array
    {
        $mine = $past->filter(function (GamePlay $p) use ($nodeIds) {
            $nodes = array_unique(array_column((array) $p->items, 'node'));

            return $nodes !== [] && array_diff($nodes, $nodeIds) === [];
        });

        return $quest + [
            'rounds' => $mine->count(),
            'best_seconds' => $mine->whereNotNull('seconds')->min('seconds'),
            'last_seconds' => $mine->first()?->seconds,
        ];
    }

    /** Days in a row with a finished round, ending today (or yesterday, while today is still open). */
    private function streak(Collection $past, string $zone): int
    {
        $days = $past->map(fn (GamePlay $p) => $p->finished_at->setTimezone($zone)->toDateString())->unique()->flip();
        $day = now()->setTimezone($zone);
        if (! $days->has($day->toDateString())) {
            $day = $day->subDay();
        }
        $n = 0;
        while ($days->has($day->toDateString())) {
            $n++;
            $day = $day->subDay();
        }

        return $n;
    }

    /**
     * Every creature of the world in its order, hatched or not (the album).
     *
     * @return list<array<string, mixed>>
     */
    public function album(string $studentId): array
    {
        $finds = CreatureFind::query()->where('student_id', $studentId)->get()->keyBy('creature_key');
        $out = [];
        foreach ($this->catalog->islands() as $island) {
            foreach ($island->creatures as $c) {
                $out[] = $this->creature($c, $finds) + ['island' => $island->key, 'island_name' => $island->name()];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function creatureOf(string $key, Collection $finds): ?array
    {
        foreach ($this->catalog->islands() as $island) {
            foreach ($island->creatures as $c) {
                if ($c->key === $key) {
                    return $this->creature($c, $finds);
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function creature(CatalogCreature $c, Collection $finds): array
    {
        $find = $finds->get($c->key);

        return [
            'key' => $c->key,
            'name' => $c->name(),
            'emoji' => $c->emoji,
            'rarity' => $c->rarity,
            'look' => $c->look,
            'story' => $c->story(),
            'found' => $find !== null,
            'found_at' => $find?->found_at,
            'source' => $find?->source,
            'nickname' => $find?->nickname,
            'buddy' => (bool) $find?->buddy,
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
