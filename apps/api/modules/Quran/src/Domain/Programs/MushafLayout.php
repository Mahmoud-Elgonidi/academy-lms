<?php

namespace Modules\Quran\Domain\Programs;

use InvalidArgumentException;
use Modules\Quran\Contracts\DTOs\VerseRange;

/**
 * The mushaf by numbers, as a lesson's portion measures it: each surah's
 * verses, where each page, part (juz) and quarter of a hizb begins, and how
 * many words each verse has. Verses are counted through the Quran from 1
 * (its "index"). Pure: made from the platform's copy (Programs\Mushaf) or,
 * in a test, by hand. Never any of the Quran's words.
 *
 * A verse weighs, in a unit: 1 verse; its words over its page's words
 * (a page); fifteen times that (lines: Tanzil keeps no lines, so a line is a
 * fifteenth of the page's words); its words over its quarter's words (a
 * quarter of a hizb). Without word counts each verse of a page or quarter
 * weighs the same.
 */
final class MushafLayout
{
    /** Lines on a page of the Madinah mushaf. */
    public const LINES_PER_PAGE = 15;

    /** @var array<int, int> surah => the index of the verse before its first */
    private array $offsets = [];

    /** @var list<int> by index - 1 */
    private array $surahAt = [];

    /** @var array<int, int> page => its words */
    private array $pageWords = [];

    /** @var array<int, int> quarter => its words */
    private array $quarterWords = [];

    private int $total = 0;

    /**
     * @param  array<int, int>  $surahVerses  surah => its verses, in order from 1
     * @param  list<int>  $pageStarts  the index each page begins at, in order
     * @param  list<int>  $juzStarts  the index each part begins at, in order
     * @param  list<int>  $quarterStarts  the index each quarter of a hizb begins at (empty: unknown)
     * @param  list<int>  $words  each verse's words, by index - 1 (empty: unknown)
     */
    public function __construct(
        public readonly array $surahVerses,
        public readonly array $pageStarts,
        public readonly array $juzStarts,
        public readonly array $quarterStarts = [],
        public readonly array $words = [],
    ) {
        foreach ($surahVerses as $surah => $verses) {
            $this->offsets[$surah] = $this->total;
            for ($v = 1; $v <= $verses; $v++) {
                $this->surahAt[] = $surah;
            }
            $this->total += $verses;
        }
        if ($pageStarts === [] || $pageStarts[0] !== 1 || $juzStarts === [] || $juzStarts[0] !== 1) {
            throw new InvalidArgumentException('A mushaf begins its first page and part at its first verse.');
        }
        for ($i = 1; $i <= $this->total; $i++) {
            $page = $this->pageOf($i);
            $this->pageWords[$page] = ($this->pageWords[$page] ?? 0) + $this->wordsOf($i);
            if ($quarterStarts !== []) {
                $quarter = self::at($quarterStarts, $i);
                $this->quarterWords[$quarter] = ($this->quarterWords[$quarter] ?? 0) + $this->wordsOf($i);
            }
        }
    }

    public function total(): int
    {
        return $this->total;
    }

    public function pageCount(): int
    {
        return count($this->pageStarts);
    }

    public function hasQuarters(): bool
    {
        return $this->quarterStarts !== [];
    }

    /** The index of a surah's verse; null when it is not there. */
    public function index(int $surah, int $verse): ?int
    {
        return isset($this->offsets[$surah]) && $verse >= 1 && $verse <= $this->surahVerses[$surah] ? $this->offsets[$surah] + $verse : null;
    }

    public function surahOf(int $index): int
    {
        return $this->surahAt[$index - 1] ?? throw new InvalidArgumentException("No verse {$index}");
    }

    public function verseOf(int $index): int
    {
        return $index - $this->offsets[$this->surahOf($index)];
    }

    public function pageOf(int $index): int
    {
        return self::at($this->pageStarts, $index);
    }

    public function juzOf(int $index): int
    {
        return self::at($this->juzStarts, $index);
    }

    /** @return array{0: int, 1: int} the first and last index of a page */
    public function pageSpan(int $page): array
    {
        if ($page < 1 || $page > $this->pageCount()) {
            throw new InvalidArgumentException("No page {$page}");
        }

        return [$this->pageStarts[$page - 1], ($this->pageStarts[$page] ?? $this->total + 1) - 1];
    }

    public function wordsOf(int $index): int
    {
        return max(1, $this->words[$index - 1] ?? 1);
    }

    /** What a verse weighs in a portion's unit (see the class). */
    public function weight(int $index, string $unit): float
    {
        return match ($unit) {
            'verses', 'lesson' => 1.0,
            'page' => $this->wordsOf($index) / $this->pageWords[$this->pageOf($index)],
            'lines' => self::LINES_PER_PAGE * $this->wordsOf($index) / $this->pageWords[$this->pageOf($index)],
            'quarter_hizb' => $this->hasQuarters()
                ? $this->wordsOf($index) / $this->quarterWords[self::at($this->quarterStarts, $index)]
                : throw new InvalidArgumentException('This mushaf has no quarters of a hizb.'),
            default => throw new InvalidArgumentException("No unit {$unit}"),
        };
    }

    /**
     * Verses by index as passages: each run of verses that follow one another in one surah.
     *
     * @param  list<int>  $indexes  in the order they are read
     * @return list<VerseRange>
     */
    public function ranges(array $indexes): array
    {
        $ranges = [];
        $run = null;
        foreach ($indexes as $index) {
            [$surah, $verse] = [$this->surahOf($index), $this->verseOf($index)];
            if ($run !== null && $run[0] === $surah && $verse === $run[2] + 1) {
                $run[2] = $verse;

                continue;
            }
            if ($run !== null) {
                $ranges[] = VerseRange::of(...$run);
            }
            $run = [$surah, $verse, $verse];
        }
        if ($run !== null) {
            $ranges[] = VerseRange::of(...$run);
        }

        return $ranges;
    }

    /**
     * The pages these verses are on, in the order first met.
     *
     * @param  list<int>  $indexes
     * @return list<int>
     */
    public function pagesOf(array $indexes): array
    {
        $pages = [];
        foreach ($indexes as $index) {
            $pages[$this->pageOf($index)] = true;
        }

        return array_keys($pages);
    }

    /**
     * How many pages passages take, by their verses' words.
     *
     * @param  list<VerseRange>  $ranges
     */
    public function pagesIn(array $ranges): float
    {
        $pages = 0.0;
        foreach ($ranges as $range) {
            for ($verse = $range->from; $verse <= $range->to; $verse++) {
                $index = $this->index($range->surah, $verse);
                $pages += $index === null ? 0.0 : $this->weight($index, 'page');
            }
        }

        return $pages;
    }

    /** Which of the runs beginning at $starts (in order) $at falls in, from 1. */
    private static function at(array $starts, int $at): int
    {
        [$low, $high] = [0, count($starts) - 1];
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $starts[$mid] <= $at ? $low = $mid : $high = $mid - 1;
        }

        return $low + 1;
    }
}
