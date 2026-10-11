<?php

namespace Modules\Games\Domain;

use Modules\SharedKernel\Games\CatalogGame;

/**
 * A round's rules, pure (docs/architecture/02, section 18): which of the
 * learner's items a game is handed, how it is scored, and when stars hatch
 * an egg.
 */
final class Round
{
    /** Stars that hatch the next creature on the island played. */
    public const STARS_PER_EGG = 12;

    /** A round longer than this counts only this long against the day's minutes. */
    public const MAX_SECONDS = 1200;

    /**
     * The items for a round of this game: those of the kinds it plays, in the
     * given order (most urgent first), at most its maximum; null when there
     * are fewer than its minimum.
     *
     * @param  list<object>  $items  each with its kind (ItemKind)
     * @return list<object>|null
     */
    public static function pick(CatalogGame $game, array $items): ?array
    {
        $fit = array_values(array_filter($items, fn (object $item) => $game->plays($item->kind)));
        if (count($fit) < $game->minItems) {
            return null;
        }

        return array_slice($fit, 0, $game->maxItems);
    }

    /**
     * How the round went: an item counts right when its first try was
     * right; an item never tried counts wrong.
     *
     * @param  list<string>  $itemIds  the round's items
     * @param  list<array{item: string, correct: bool}>  $answers  every try, in order
     * @return array{correct: int, total: int, stars: int, first: array<string, bool>}
     */
    public static function score(array $itemIds, array $answers): array
    {
        $first = [];
        foreach ($answers as $answer) {
            if (in_array($answer['item'], $itemIds, true) && ! array_key_exists($answer['item'], $first)) {
                $first[$answer['item']] = $answer['correct'] === true;
            }
        }
        $total = count($itemIds);
        $correct = count(array_filter($first));

        return ['correct' => $correct, 'total' => $total, 'stars' => self::stars($correct, $total, count($first)), 'first' => $first];
    }

    public static function stars(int $correct, int $total, int $tried): int
    {
        if ($total === 0 || $tried === 0) {
            return 0;
        }
        $share = $correct / $total;

        return match (true) {
            $share >= 0.9 => 3,
            $share >= 0.6 => 2,
            default => 1,
        };
    }
}
