<?php

namespace Modules\Learning\Domain\Hifz;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * When a memorized page is next due for review (pure): spaced repetition in
 * the manner of FSRS, made small. Each page has a stability (the days until
 * it would be remembered nine times in ten; the next review comes then) and
 * a difficulty (1 easy to 10 hard). A review's grade (1 again, 2 hard, 3
 * good, 4 easy) moves them: a page recited well when it was nearly
 * forgotten grows most; one forgotten falls back to days. A page is
 * memorized once it holds for a week (or a report or the teacher says so),
 * and stays memorized: a page forgotten again shows as weak, not new.
 */
final class Scheduler
{
    public const AGAIN = 1;

    public const HARD = 2;

    public const GOOD = 3;

    public const EASY = 4;

    /** Days a page may wait at most before its next review. */
    public const MAX_DAYS = 180;

    /** Stability, in days, from which a page counts as memorized. */
    public const MEMORIZED_DAYS = 7.0;

    /** The stability of a page the teacher marks as known: strong, good or weak. */
    public const MARKED = ['strong' => 30.0, 'good' => 10.0, 'weak' => 3.0];

    /** How strong a page is now, from how likely it is remembered today. */
    public const STRONG = 0.9;

    public const FADING = 0.75;

    private const FIRST = [self::AGAIN => 0.4, self::HARD => 1.2, self::GOOD => 3.0, self::EASY => 8.0];

    /**
     * A page reviewed for the first time.
     *
     * @return array{state: string, stability: float, difficulty: float, due_at: CarbonImmutable, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int}
     */
    public static function first(int $grade, CarbonImmutable $at, bool $memorized = false): array
    {
        self::grade($grade);
        $stability = self::FIRST[$grade];

        return self::card($memorized || $stability >= self::MEMORIZED_DAYS ? 'memorized' : 'learning', $stability, self::clamp(5.5 - 1.2 * ($grade - self::GOOD)), $at, 1, $grade === self::AGAIN ? 1 : 0);
    }

    /**
     * A page the teacher marks as known (strong, good or weak), as if it had just been reviewed.
     *
     * @return array{state: string, stability: float, difficulty: float, due_at: CarbonImmutable, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int}
     */
    public static function marked(string $strength, CarbonImmutable $at): array
    {
        $stability = self::MARKED[$strength] ?? throw new InvalidArgumentException("No such strength: {$strength}");

        return self::card('memorized', $stability, 5.0, $at, 0, 0);
    }

    /**
     * A page reviewed again.
     *
     * @param  array{state: string, stability: float, difficulty: float, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int}  $card
     * @return array{state: string, stability: float, difficulty: float, due_at: CarbonImmutable, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int}
     */
    public static function next(array $card, int $grade, CarbonImmutable $at, bool $memorized = false): array
    {
        self::grade($grade);
        $stability = max(0.1, (float) $card['stability']);
        $elapsed = max(0.0, $card['last_reviewed_at']->diffInSeconds($at, false) / 86400);
        $recall = self::recall($stability, $elapsed);

        if ($grade === self::AGAIN) {
            // Forgotten: back to days, a little more than nothing the better it was known.
            $next = max(0.3, $stability * 0.15 * (1 + $recall));
            $difficulty = self::clamp($card['difficulty'] + 1.2);

            return self::card($memorized ? 'memorized' : $card['state'], $next, $difficulty, $at, $card['reviews'] + 1, $card['lapses'] + 1);
        }

        $difficulty = self::clamp($card['difficulty'] - 0.6 * ($grade - self::GOOD));
        // It grows most when the page was nearly forgotten, less when it was still fresh, less for a hard page.
        $growth = exp(1.1) * (11 - $difficulty) * $stability ** -0.15 * (exp(1 - $recall) - 1);
        $growth *= match ($grade) {
            self::HARD => 0.5,
            self::EASY => 1.4,
            default => 1.0,
        };
        $next = max($stability * (1 + $growth), $stability + 0.2 * ($grade - 1));
        $state = $memorized || $card['state'] === 'memorized' || ($grade >= self::GOOD && $next >= self::MEMORIZED_DAYS) ? 'memorized' : 'learning';

        return self::card($state, $next, $difficulty, $at, $card['reviews'] + 1, $card['lapses']);
    }

    /** How likely a page is remembered $elapsed days after its last review (1 just after; 0.9 when it is due). */
    public static function recall(float $stability, float $elapsed): float
    {
        return (1 + $elapsed / (9 * max(0.1, $stability))) ** -1;
    }

    /** How a page shows on the map now: new (never), learning, or a memorized page strong, fading or weak. */
    public static function strength(?array $card, CarbonImmutable $now): string
    {
        if ($card === null) {
            return 'new';
        }
        if ($card['state'] !== 'memorized') {
            return 'learning';
        }
        $recall = self::recall((float) $card['stability'], max(0.0, $card['last_reviewed_at']->diffInSeconds($now, false) / 86400));

        return $recall >= self::STRONG ? 'strong' : ($recall >= self::FADING ? 'fading' : 'weak');
    }

    /** A recitation's grade from the share of its words said right. */
    public static function gradeFor(float $right): int
    {
        return match (true) {
            $right >= 0.95 => self::EASY,
            $right >= 0.85 => self::GOOD,
            $right >= 0.6 => self::HARD,
            default => self::AGAIN,
        };
    }

    /** @return array{state: string, stability: float, difficulty: float, due_at: CarbonImmutable, last_reviewed_at: CarbonImmutable, reviews: int, lapses: int} */
    private static function card(string $state, float $stability, float $difficulty, CarbonImmutable $at, int $reviews, int $lapses): array
    {
        $days = (int) min(self::MAX_DAYS, max(1, round($stability)));

        return [
            'state' => $state, 'stability' => round($stability, 3), 'difficulty' => round($difficulty, 3),
            'due_at' => $at->addDays($days), 'last_reviewed_at' => $at, 'reviews' => $reviews, 'lapses' => $lapses,
        ];
    }

    private static function clamp(float $difficulty): float
    {
        return max(1.0, min(10.0, $difficulty));
    }

    private static function grade(int $grade): void
    {
        if ($grade < self::AGAIN || $grade > self::EASY) {
            throw new InvalidArgumentException("No such grade: {$grade}");
        }
    }
}
