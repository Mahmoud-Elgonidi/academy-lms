<?php

namespace Modules\Games\Queries;

use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GamePlay;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\LearningApi;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * What a learner can practise and with which games (docs/architecture/02,
 * section 18): Learning says what to practise now (PracticeFocus: homework,
 * the last lesson, reviews, where the course stands), Curriculum has those
 * lessons' items, and the catalog's live games for the learner's look play
 * them. Each focus with items becomes a quest with the game that suits it
 * (a full round first, then the game played least this week, for variety);
 * "free" play takes every focus's items together.
 */
final class PracticePool
{
    public const QUESTS = 4;

    /** A round with fewer items than this comes after fuller ones. */
    public const FULL = 3;

    public function __construct(
        private readonly LearningApi $learning,
        private readonly CurriculumApi $curriculum,
        private readonly GameCatalog $catalog,
    ) {}

    /** @return list<CatalogGame> the live games made for this look */
    public function games(string $stage): array
    {
        return array_values(array_filter($this->catalog->games(), fn (CatalogGame $g) => $g->fits($stage)));
    }

    /** @return list<PracticeFocus> */
    public function focus(string $studentId): array
    {
        return $this->learning->practiceFocus($studentId);
    }

    /**
     * The items on these lessons, in their order.
     *
     * @param  list<string>  $nodeIds
     * @return list<PracticeItem>
     */
    public function itemsOf(array $nodeIds): array
    {
        $missing = array_values(array_diff($nodeIds, array_keys($this->byNode)));
        foreach ($this->curriculum->practiceItems($missing) + array_fill_keys($missing, []) as $nodeId => $list) {
            $this->byNode[$nodeId] = $list;
        }
        $out = [];
        foreach ($nodeIds as $nodeId) {
            foreach ($this->byNode[$nodeId] ?? [] as $item) {
                $out[$item->id] = $item;
            }
        }

        return array_values($out);
    }

    /** @var array<string, list<PracticeItem>> */
    private array $byNode = [];

    /**
     * The learner's quests: each focus with items, and the game for it.
     *
     * @param  list<PracticeFocus>  $focus
     * @param  list<CatalogGame>  $games
     * @return list<array{focus: PracticeFocus, game: CatalogGame, items: int}>
     */
    public function quests(string $studentId, array $focus, array $games): array
    {
        $recent = GamePlay::query()->where('student_id', $studentId)->where('started_at', '>=', now()->subWeek())
            ->selectRaw('game_id, count(*) as n')->groupBy('game_id')->pluck('n', 'game_id');
        $quests = [];
        foreach ($focus as $f) {
            $items = $this->itemsOf($f->nodeIds);
            $fit = array_values(array_filter($games, fn (CatalogGame $g) => Round::pick($g, $items) !== null));
            if ($fit === []) {
                continue;
            }
            $size = fn (CatalogGame $g) => count(Round::pick($g, $items) ?? []);
            usort($fit, fn (CatalogGame $a, CatalogGame $b) => [$size($a) < self::FULL, (int) ($recent[$a->id] ?? 0), -$size($a), $a->sort] <=> [$size($b) < self::FULL, (int) ($recent[$b->id] ?? 0), -$size($b), $b->sort]);
            $quests[] = ['focus' => $f, 'game' => $fit[0], 'items' => count($items)];
            if (count($quests) === self::QUESTS) {
                break;
            }
        }

        return $quests;
    }

    /**
     * Every focus's items together, most urgent first (free play).
     *
     * @param  list<PracticeFocus>  $focus
     * @return list<PracticeItem>
     */
    public function all(array $focus): array
    {
        return $this->itemsOf(array_values(array_unique(array_merge(...array_map(fn (PracticeFocus $f) => $f->nodeIds, $focus ?: [new PracticeFocus('current', null, [], '')])))));
    }
}
