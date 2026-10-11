<?php

namespace Modules\Games\Queries;

use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\Learning\Contracts\DTOs\JourneyGamesLayer;
use Modules\Learning\Contracts\DTOs\JourneyStation;
use Modules\Learning\Contracts\JourneyGames;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * The games on a learner's journey (docs/architecture/02, section 18): on each
 * lesson they may practise, the live games for their look that can play that
 * lesson's own items (the teacher's choice for a homework first, then the
 * games of the island the level is drawn as, then the one played least this
 * week, then the fuller round); the race against yourself on each lesson;
 * the creatures of each island; the egg every Stars::PER_EGG stars of the
 * journey fill (lessons, homework and chests too, not only rounds); and
 * today's play. Nothing when the academy turned the games off or none fits.
 */
final class GamesForJourney implements JourneyGames
{
    public function __construct(
        private readonly PracticePool $pool,
        private readonly PlayAllowance $allowance,
        private readonly GameCatalog $catalog,
        private readonly GamesWorld $world,
        private readonly GuardianGlance $glance,
    ) {}

    public function layer(string $studentId, string $stage, array $stations, int $stars, bool $guardian): ?JourneyGamesLayer
    {
        $games = $this->pool->games($stage);
        if (! GameSettings::current()->enabled || $games === []) {
            return null;
        }

        $recent = GamePlay::query()->where('student_id', $studentId)->where('started_at', '>=', now()->subWeek())
            ->selectRaw('game_id, count(*) as n')->groupBy('game_id')->pluck('n', 'game_id');
        $past = GamePlay::query()->where('student_id', $studentId)->whereNotNull('finished_at')->latest('finished_at')->limit(300)
            ->get(['game_id', 'game_key', 'items', 'seconds', 'correct', 'total', 'stars', 'finished_at', 'focus']);

        $byStation = [];
        $stats = [];
        // The race on a lesson: the rounds played on that lesson alone, newest first (grouped once, not per lesson).
        $onOne = $past->groupBy(fn (GamePlay $p) => count($nodes = array_values(array_unique(array_column((array) $p->items, 'node')))) === 1 ? $nodes[0] : '');
        // Every station's items in one read (the pool keeps them per lesson).
        if ($stations !== []) {
            $this->pool->itemsOf(array_map(fn (JourneyStation $s) => $s->id, $stations));
        }
        foreach ($stations as $station) {
            /** @var JourneyStation $station */
            $items = $this->pool->itemsOf([$station->id]);
            $sizes = [];
            foreach ($games as $g) {
                $round = Round::pick($g, $items);
                if ($round !== null) {
                    $sizes[$g->id] = count($round);
                }
            }
            $fit = array_values(array_filter($games, fn (CatalogGame $g) => isset($sizes[$g->id])));
            $size = fn (CatalogGame $g) => $sizes[$g->id];
            usort($fit, fn (CatalogGame $a, CatalogGame $b) => $this->rank($a, $station, $recent, $size) <=> $this->rank($b, $station, $recent, $size));
            $byStation[$station->id] = array_map(fn (CatalogGame $g) => [
                'id' => $g->id,
                'key' => $g->key,
                'island' => $g->islandKey,
                'title' => $g->title(),
                'summary' => $g->summary(),
                'how_to_play' => $g->howToPlay(),
                'kinds' => array_map(fn ($k) => $k->value, $g->kinds),
                /** The teacher chose it for homework on this lesson. */
                'chosen' => $station->game !== null && $g->key === $station->game,
                /** Items in a round on this lesson. */
                'items' => $size($g),
            ], $fit);

            $mine = $onOne->get($station->id);
            if ($mine !== null) {
                $stats[$station->id] = [
                    'rounds' => $mine->count(),
                    'best_seconds' => $mine->whereNotNull('seconds')->min('seconds'),
                    'last_seconds' => $mine->first()?->seconds,
                    'best_stars' => (int) $mine->max('stars'),
                ];
            }
        }

        $album = $this->world->album($studentId);
        $islands = [];
        foreach (collect($album)->groupBy('island') as $key => $creatures) {
            $next = $creatures->first(fn (array $c) => ! $c['found']);
            $islands[$key] = [
                'creatures' => $creatures->values()->all(),
                'next' => $next === null ? null : ['key' => $next['key'], 'look' => $next['look'], 'rarity' => $next['rarity']],
            ];
        }
        $found = collect($album)->where('found', true);
        $buddy = $found->firstWhere('buddy', true) ?? $found->sortByDesc(fn (array $c) => $c['found_at']?->getTimestamp() ?? 0)->first();
        $hatched = CreatureFind::query()->where('student_id', $studentId)->where('source', 'stars')->count();
        $ready = intdiv($stars, Round::STARS_PER_EGG) > $hatched && $found->count() < count($album);
        $titles = [];
        foreach ($this->catalog->games() as $g) {
            $titles[$g->id] = $g->title();
        }

        return new JourneyGamesLayer(
            allowance: $this->allowance->for($studentId),
            games: $byStation,
            stats: $stats,
            islands: $islands,
            egg: ['have' => $ready ? Round::STARS_PER_EGG : $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG, 'ready' => $ready],
            buddy: $buddy,
            found: $found->count(),
            total: count($album),
            recent: $past->take(5)->map(fn (GamePlay $p) => [
                'game' => $titles[$p->game_id] ?? $p->game_key, 'stars' => (int) $p->stars, 'correct' => (int) $p->correct, 'total' => $p->total, 'at' => $p->finished_at,
            ])->values()->all(),
            limits: $guardian ? $this->limits($studentId) : null,
            glance: $guardian ? $this->glanceOf($studentId) : null,
        );
    }

    /** @return list<int|bool> the order of games on a lesson: the teacher's choice, the island's own, played least, fuller, catalog order */
    private function rank(CatalogGame $g, JourneyStation $station, $recent, callable $size): array
    {
        return [
            $station->game !== null && $g->key === $station->game ? 0 : 1,
            $station->island !== null && $g->islandKey === $station->island ? 0 : 1,
            $size($g) < PracticePool::FULL,
            (int) ($recent[$g->id] ?? 0),
            -$size($g),
            $g->sort,
        ];
    }

    /** @return array{enabled: bool, daily_minutes: ?int, night_lock: bool, academy_minutes: ?int, choices: list<int>} */
    private function limits(string $studentId): array
    {
        $limit = GameLimit::query()->find($studentId);

        return [
            'enabled' => $limit?->enabled ?? true,
            'daily_minutes' => $limit?->daily_minutes,
            'night_lock' => (bool) ($limit?->night_lock ?? false),
            'academy_minutes' => GameSettings::current()->daily_minutes,
            'choices' => SaveGameLimit::MINUTES,
        ];
    }

    /** @return array<string, mixed> */
    private function glanceOf(string $studentId): array
    {
        $g = $this->glance->for($studentId);

        return ['rounds' => $g['rounds'], 'practised' => $g['practised'], 'hardest' => $g['hardest'], 'best' => $g['best'], 'hatched' => $g['hatched']];
    }
}
