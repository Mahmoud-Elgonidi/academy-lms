<?php

namespace Modules\Games\Queries;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * What a guardian sees of their child's games (the approved design,
 * Guardian; docs/architecture/02, section 18): today's minutes against the
 * limit, what the child practised this week (the lesson they went back to
 * most and in how many games, the question that was hardest for them first
 * time, their best round), and a creature hatched today. From the rounds
 * themselves; never another child's.
 */
final class GuardianGlance
{
    public const DAYS = 7;

    public function __construct(
        private readonly PlayAllowance $allowance,
        private readonly PracticePool $pool,
        private readonly GameCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function for(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $week = GamePlay::query()->where('student_id', $studentId)->whereNotNull('finished_at')
            ->where('finished_at', '>=', $now->subDays(self::DAYS))->latest('finished_at')->get();
        $titles = [];
        foreach ($this->catalog->games() as $g) {
            $titles[$g->id] = $g->title();
        }

        return [
            'allowance' => $this->allowance->for($studentId, $now),
            'rounds' => $week->count(),
            'practised' => $this->practised($studentId, $week),
            'hardest' => $this->hardest($week),
            'best' => ($b = $week->where('correct', '>', 0)->sortByDesc('correct')->first()) === null ? null
                : ['correct' => (int) $b->correct, 'total' => $b->total, 'game' => $titles[$b->game_id] ?? $b->game_key],
            'hatched' => $this->hatchedToday($studentId, $now),
        ];
    }

    /**
     * The lesson the child went back to most this week: its title, how many rounds, in how many games.
     *
     * @return array{title: string, rounds: int, games: int}|null
     */
    private function practised(string $studentId, $week): ?array
    {
        $byNode = [];
        foreach ($week as $play) {
            foreach (array_unique(array_column((array) $play->items, 'node')) as $node) {
                $byNode[$node]['rounds'] = ($byNode[$node]['rounds'] ?? 0) + 1;
                $byNode[$node]['games'][$play->game_key] = true;
            }
        }
        if ($byNode === []) {
            return null;
        }
        uasort($byNode, fn ($a, $b) => $b['rounds'] <=> $a['rounds']);
        // A lesson's title, from what Learning says the child practises (homework, last lesson, reviews, current).
        $titles = [];
        foreach ($this->pool->focus($studentId) as $f) {
            foreach ($f->nodeIds as $id) {
                $titles[$id] ??= $f->title ?: null;
            }
        }
        $node = collect(array_keys($byNode))->first(fn ($id) => isset($titles[$id]));
        $title = $node === null ? null : $titles[$node];

        return $title === null ? null : ['title' => $title, 'rounds' => $byNode[$node]['rounds'], 'games' => count($byNode[$node]['games'])];
    }

    /**
     * The question the child got wrong first time most often this week (at least once).
     *
     * @return array{text: string, wrong: int}|null
     */
    private function hardest($week): ?array
    {
        $wrong = [];
        $nodeOf = [];
        foreach ($week as $play) {
            foreach ((array) $play->items as $item) {
                $nodeOf[$item['id']] = $item['node'];
            }
            foreach (Round::score($play->itemIds(), (array) $play->answers)['first'] as $item => $right) {
                if (! $right) {
                    $wrong[$item] = ($wrong[$item] ?? 0) + 1;
                }
            }
        }
        if ($wrong === []) {
            return null;
        }
        arsort($wrong);
        $id = (string) array_key_first($wrong);
        $item = collect($this->pool->itemsOf([$nodeOf[$id] ?? '']))->first(fn (PracticeItem $i) => $i->id === $id);

        return $item === null ? null : ['text' => $item->summary(), 'wrong' => $wrong[$id]];
    }

    /** @return array<string, mixed>|null the last creature hatched today on the child's clock */
    private function hatchedToday(string $studentId, CarbonImmutable $now): ?array
    {
        $dayStart = $now->setTimezone($this->allowance->zone($studentId))->startOfDay()->utc();
        $find = CreatureFind::query()->where('student_id', $studentId)->where('found_at', '>=', $dayStart)->latest('found_at')->first();
        if ($find === null) {
            return null;
        }
        foreach ($this->catalog->islands() as $island) {
            foreach ($island->creatures as $c) {
                if ($c->key === $find->creature_key) {
                    return [
                        'key' => $c->key, 'name' => $find->nickname ?: $c->name(), 'kind_name' => $c->name(), 'look' => $c->look,
                        'island' => $island->name(), 'source' => $find->source,
                        'found' => CreatureFind::query()->where('student_id', $studentId)->count(),
                    ];
                }
            }
        }

        return null;
    }
}
