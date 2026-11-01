<?php

namespace Modules\Quran\Programs;

use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Hifz\HifzPages;

/**
 * The mushaf as a strip of its parts (30 in the Madinah mushaf) for the
 * screens, by numbers from the platform's copy alone: where a program's
 * scope begins and ends and which way it goes, the part a learner is in,
 * how much of each part they have memorized, and a part's first and last
 * verse (to set a scope by parts). Empty without the platform's copy.
 */
final class JuzStrip
{
    public function __construct(
        private readonly Mushaf $mushaf,
        private readonly HifzPages $pages,
    ) {}

    public function available(): bool
    {
        return $this->mushaf->layout() !== null;
    }

    /** The parts of the mushaf (30; fewer in a test's made-up copy). */
    public function count(): int
    {
        return count($this->mushaf->layout()?->juzStarts ?? []);
    }

    /**
     * A program's scope on the strip; null on the Noorani Qaida or without the platform's copy.
     *
     * @return ?array{from: int, to: int, direction: string, cells: list<array{juz: int, in: bool, edge: ?string}>}
     */
    public function scope(?int $fromSurah, int $fromVerse, ?int $toSurah, int $toVerse, string $direction): ?array
    {
        $layout = $this->mushaf->layout();
        $from = $fromSurah === null || $layout === null ? null : $layout->index($fromSurah, $fromVerse);
        $to = $toSurah === null || $layout === null ? null : $layout->index($toSurah, $toVerse);
        if ($from === null || $to === null || $to < $from) {
            return null;
        }
        [$a, $b] = [$layout->juzOf($from), $layout->juzOf($to)];
        // Backward programs begin at the scope's last surah: its part is where they start.
        [$start, $end] = $direction === 'backward' ? [$b, $a] : [$a, $b];
        $cells = [];
        for ($juz = 1; $juz <= $this->count(); $juz++) {
            $cells[] = ['juz' => $juz, 'in' => $juz >= $a && $juz <= $b, 'edge' => $juz === $start ? 'start' : ($juz === $end ? 'end' : null)];
        }

        return ['from' => $a, 'to' => $b, 'direction' => $direction, 'cells' => $cells];
    }

    /** The part a position is in; null on the Noorani Qaida or without the copy. */
    public function juzOf(?Position $at): ?int
    {
        $layout = $this->mushaf->layout();
        $index = $at === null || $at->isLesson() || $layout === null ? null : $layout->index((int) $at->surah, $at->verse);

        return $index === null ? null : $layout->juzOf($index);
    }

    /**
     * How much of each part is memorized, 0 to 1, by part.
     *
     * @param  list<int>  $memorizedPages
     * @return array<int, float>
     */
    public function shares(array $memorizedPages): array
    {
        $total = [];
        $known = [];
        $memorized = array_flip($memorizedPages);
        foreach ($this->pages->layout() as ['page' => $page, 'juz' => $juz]) {
            $total[$juz] = ($total[$juz] ?? 0) + 1;
            $known[$juz] = ($known[$juz] ?? 0) + (isset($memorized[$page]) ? 1 : 0);
        }
        $shares = [];
        foreach ($total as $juz => $n) {
            $shares[$juz] = $n === 0 ? 0.0 : round($known[$juz] / $n, 3);
        }

        return $shares;
    }

    /**
     * A part's first and last verse, to set a scope by parts.
     *
     * @return ?array{from: array{surah: int, verse: int}, to: array{surah: int, verse: int}}
     */
    public function bounds(int $fromJuz, int $toJuz): ?array
    {
        $layout = $this->mushaf->layout();
        $count = $this->count();
        if ($layout === null || $fromJuz < 1 || $toJuz > $count || $fromJuz > $toJuz) {
            return null;
        }
        $first = $layout->juzStarts[$fromJuz - 1];
        $last = ($layout->juzStarts[$toJuz] ?? $layout->total() + 1) - 1;

        return [
            'from' => ['surah' => $layout->surahOf($first), 'verse' => $layout->verseOf($first)],
            'to' => ['surah' => $layout->surahOf($last), 'verse' => $layout->verseOf($last)],
        ];
    }
}
