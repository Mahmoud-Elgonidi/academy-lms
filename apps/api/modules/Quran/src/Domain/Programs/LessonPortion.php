<?php

namespace Modules\Quran\Domain\Programs;

use InvalidArgumentException;

/**
 * Each lesson's portion, made from the program and where the learner is
 * (docs/architecture/02, section 26), by the mushaf's numbers alone:
 *
 * - The program's verses are taught in an order: forward, through the
 *   mushaf from the scope's first verse; backward, by surah from the last
 *   surah of the scope to its first (an-Nas, al-Falaq, …), each surah's
 *   verses in their own order.
 * - New: from where the learner is, verse after verse while the next one's
 *   middle stays within the amount (so a portion comes out nearest the
 *   amount, never empty). Memorizing (hifz, talqin) closes a surah in its
 *   own lesson once the portion is half done, and never leaves a surah's
 *   last verses for later when they weigh less than half the amount. The
 *   scope's end ends it; past the end the program is finished.
 * - Near review: the verses just before where the learner is, as many as
 *   the last N portions would be.
 * - Far review: the next pages of what they have memorized (outside the
 *   new and near parts), cycling: forward through the mushaf, or backward
 *   from its end, after the last page the far review reached.
 * - The exam stops the new part completes: a surah's or a part's last
 *   verse to be taught in this program.
 *
 * The Noorani Qaida has no verses: its lessons are numbered, one a lesson.
 */
final class LessonPortion
{
    private const EPSILON = 1e-9;

    /**
     * The scope's verses (indexes) in the order they are taught.
     *
     * @return list<int>
     */
    public static function order(ProgramShape $program, MushafLayout $mushaf): array
    {
        $from = $program->fromSurah === null ? null : $mushaf->index($program->fromSurah, $program->fromVerse);
        $to = $program->toSurah === null ? null : $mushaf->index($program->toSurah, $program->toVerse);
        if ($from === null || $to === null || $to < $from) {
            throw new InvalidArgumentException('The program\'s scope is not in this mushaf.');
        }
        if ($program->direction === 'forward') {
            return range($from, $to);
        }
        $order = [];
        for ($surah = $program->toSurah; $surah >= $program->fromSurah; $surah--) {
            $first = $surah === $program->fromSurah ? $program->fromVerse : 1;
            $last = $surah === $program->toSurah ? $program->toVerse : $mushaf->surahVerses[$surah];
            for ($verse = $first; $verse <= $last; $verse++) {
                $order[] = (int) $mushaf->index($surah, $verse);
            }
        }

        return $order;
    }

    /**
     * Where a learner starts: the first verse in the program's order not on a page they have memorized when it
     * memorizes, else the scope's first; null when all of it is memorized already.
     *
     * @param  list<int>  $memorizedPages
     */
    public static function start(ProgramShape $program, MushafLayout $mushaf, array $memorizedPages = []): ?Position
    {
        if ($program->isNoorani()) {
            return Position::lesson($program->fromVerse);
        }
        $known = $program->memorizes() ? array_flip($memorizedPages) : [];
        foreach (self::order($program, $mushaf) as $index) {
            if (! isset($known[$mushaf->pageOf($index)])) {
                return Position::verse($mushaf->surahOf($index), $mushaf->verseOf($index));
            }
        }

        return null;
    }

    /** Whether a position is one of the program's (a verse of its scope, or one of its lessons). */
    public static function holds(ProgramShape $program, MushafLayout $mushaf, Position $at): bool
    {
        if ($program->isNoorani()) {
            return $at->isLesson() && $at->verse >= $program->fromVerse && $at->verse <= $program->toVerse;
        }
        $index = $at->isLesson() ? null : $mushaf->index((int) $at->surah, $at->verse);

        return $index !== null && in_array($index, self::order($program, $mushaf), true);
    }

    /**
     * @param  ?Position  $at  where the learner is; null once the program is done
     * @param  list<int>  $memorizedPages  the pages they have memorized (the far review cycles over them)
     * @param  ?int  $farCursor  the last page the far review reached
     */
    public static function make(ProgramShape $program, ?Position $at, MushafLayout $mushaf, array $memorizedPages = [], ?int $farCursor = null): Portion
    {
        if ($program->isNoorani()) {
            return self::noorani($program, $at);
        }
        $order = self::order($program, $mushaf);
        $n = count($order);
        $pos = $n;
        if ($at !== null && $program->hasNew()) {
            $index = $at->isLesson() ? null : $mushaf->index((int) $at->surah, $at->verse);
            $pos = $index === null ? null : (array_flip($order)[$index] ?? null);
            if ($pos === null) {
                throw new InvalidArgumentException('The position is not in the program\'s scope.');
            }
        }

        [$new, $end] = $program->hasNew() ? self::newPart($program, $mushaf, $order, $pos) : [[], $pos];
        $near = $program->hasNew() ? self::nearPart($program, $mushaf, $order, $pos) : [];
        $newPages = $mushaf->pagesOf($new);
        $nearPages = $mushaf->pagesOf($near);
        $farPages = self::farPages($program, $memorizedPages, $farCursor, array_merge($newPages, $nearPages), $mushaf->pageCount());
        $far = [];
        foreach ($farPages as $page) {
            [$first, $last] = $mushaf->pageSpan($page);
            array_push($far, ...range($first, $last));
        }

        return new Portion(
            new: $mushaf->ranges($new),
            near: $mushaf->ranges($near),
            far: $mushaf->ranges($far),
            newPages: $newPages,
            nearPages: $nearPages,
            farPages: $farPages,
            next: $program->hasNew() && $end < $n ? Position::verse($mushaf->surahOf($order[$end]), $mushaf->verseOf($order[$end])) : null,
            finished: $program->hasNew() && $pos >= $n,
            stops: self::stops($program, $mushaf, $order, $pos, $end),
            progress: $program->hasNew() && $n > 0 ? round(min($pos, $n) / $n, 4) : 0.0,
            completedPages: self::completed($mushaf, $order, $newPages, $end),
        );
    }

    /**
     * @param  list<int>  $order
     * @return array{0: list<int>, 1: int} the new verses, and where the next portion starts in the order
     */
    private static function newPart(ProgramShape $program, MushafLayout $mushaf, array $order, int $pos): array
    {
        $n = count($order);
        $amount = $program->newAmount;
        $new = [];
        $taken = 0.0;
        $i = $pos;
        while ($i < $n) {
            $weight = $mushaf->weight($order[$i], $program->unit);
            if ($new !== [] && $taken + $weight / 2 > $amount + self::EPSILON) {
                break;
            }
            $new[] = $order[$i];
            $taken += $weight;
            $i++;
            $surahEnds = $i < $n && $mushaf->surahOf($order[$i]) !== $mushaf->surahOf($order[$i - 1]);
            if ($program->stopsAtSurahEnd() && $surahEnds && $taken >= $amount / 2 - self::EPSILON) {
                break;
            }
        }
        // A surah is never left with a scrap: its last verses come now when they weigh less than half the amount.
        if ($program->stopsAtSurahEnd() && $new !== [] && $i < $n) {
            $surah = $mushaf->surahOf($order[$i - 1]);
            $rest = [];
            $weight = 0.0;
            for ($j = $i; $j < $n && $mushaf->surahOf($order[$j]) === $surah; $j++) {
                $rest[] = $order[$j];
                $weight += $mushaf->weight($order[$j], $program->unit);
            }
            if ($rest !== [] && $weight < $amount / 2 - self::EPSILON) {
                array_push($new, ...$rest);
                $i += count($rest);
            }
        }

        return [$new, $i];
    }

    /**
     * @param  list<int>  $order
     * @return list<int> the verses just before where the learner is, in the order taught
     */
    private static function nearPart(ProgramShape $program, MushafLayout $mushaf, array $order, int $pos): array
    {
        if ($program->nearPortions < 1) {
            return [];
        }
        $amount = $program->nearPortions * $program->newAmount;
        $near = [];
        $taken = 0.0;
        for ($j = min($pos, count($order)) - 1; $j >= 0; $j--) {
            $weight = $mushaf->weight($order[$j], $program->unit);
            if ($near !== [] && $taken + $weight / 2 > $amount + self::EPSILON) {
                break;
            }
            $near[] = $order[$j];
            $taken += $weight;
        }

        return array_reverse($near);
    }

    /**
     * @param  list<int>  $memorized
     * @param  list<int>  $taken  the new and near parts' pages
     * @return list<int>
     */
    private static function farPages(ProgramShape $program, array $memorized, ?int $cursor, array $taken, int $pageCount): array
    {
        if ($program->farPages < 1) {
            return [];
        }
        $pages = array_values(array_diff(array_unique(array_filter($memorized, fn ($p) => $p >= 1 && $p <= $pageCount)), $taken));
        if ($pages === []) {
            return [];
        }
        $forward = $program->direction === 'forward';
        $forward ? sort($pages) : rsort($pages);
        $start = 0;
        if ($cursor !== null) {
            $start = count($pages);
            foreach ($pages as $i => $page) {
                if ($forward ? $page > $cursor : $page < $cursor) {
                    $start = $i;
                    break;
                }
            }
            $start %= count($pages);
        }
        $far = [];
        for ($k = 0; $k < min($program->farPages, count($pages)); $k++) {
            $far[] = $pages[($start + $k) % count($pages)];
        }

        return $far;
    }

    /**
     * The surahs and parts whose last verse to be taught in this program falls in the new part.
     *
     * @param  list<int>  $order
     * @return list<array{kind: string, number: int}>
     */
    private static function stops(ProgramShape $program, MushafLayout $mushaf, array $order, int $pos, int $end): array
    {
        if ($program->examStops === 'none' || $end <= $pos) {
            return [];
        }
        $of = fn (int $index): int => $program->examStops === 'juz' ? $mushaf->juzOf($index) : $mushaf->surahOf($index);
        $last = [];
        foreach ($order as $k => $index) {
            $last[$of($index)] = $k;
        }
        $stops = [];
        foreach ($last as $number => $k) {
            if ($k >= $pos && $k < $end) {
                $stops[] = ['kind' => $program->examStops, 'number' => $number];
            }
        }

        return $stops;
    }

    /**
     * The new part's pages with no verse of the program's left to teach after it.
     *
     * @param  list<int>  $order
     * @param  list<int>  $pages
     * @return list<int>
     */
    private static function completed(MushafLayout $mushaf, array $order, array $pages, int $end): array
    {
        if ($pages === []) {
            return [];
        }
        $last = [];
        foreach ($order as $k => $index) {
            $last[$mushaf->pageOf($index)] = $k;
        }

        return array_values(array_filter($pages, fn (int $page) => ($last[$page] ?? PHP_INT_MAX) < $end));
    }

    private static function noorani(ProgramShape $program, ?Position $at): Portion
    {
        $count = $program->toVerse - $program->fromVerse + 1;
        if ($at === null || $at->verse > $program->toVerse) {
            return new Portion(finished: true, progress: 1.0);
        }
        if (! $at->isLesson() || $at->verse < $program->fromVerse) {
            throw new InvalidArgumentException('The position is not one of the program\'s lessons.');
        }
        $next = $at->verse + max(1, (int) $program->newAmount);

        return new Portion(
            next: $next <= $program->toVerse ? Position::lesson($next) : null,
            lesson: $at->verse,
            progress: round(($at->verse - $program->fromVerse) / $count, 4),
        );
    }
}
