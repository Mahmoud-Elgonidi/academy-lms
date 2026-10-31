<?php

namespace Modules\Quran\Contracts;

use Modules\Quran\Contracts\DTOs\QuranPage;
use Modules\Quran\Contracts\DTOs\QuranPassage;
use Modules\Quran\Contracts\DTOs\VerseRange;

/**
 * The Quran as the module keeps it (docs/architecture/02, section 26): the
 * platform's copy of the text (Texts\QuranText) and the passages the
 * academy's lessons read. Nothing below the module depends on it; it is the
 * module's own way in for its screens, jobs and later parts.
 */
interface QuranApi
{
    /** Whether the platform has its copy of the text. */
    public function available(): bool;

    /**
     * The surahs the platform has, in order; empty until its copy is in place.
     *
     * @return list<array{number: int, name_ar: string, name_en: string, verses: int}>
     */
    public function surahs(): array;

    /** A passage as it is read; null when its verses are not there. */
    public function passage(VerseRange $source): ?QuranPassage;

    /**
     * The mushaf's pages (604) and the part each begins in, in order; empty until the copy is in place.
     *
     * @return list<array{page: int, juz: int}>
     */
    public function quranPages(): array;

    /**
     * The pages a passage is on, in order; empty for verses not there.
     *
     * @return list<int>
     */
    public function quranPagesOf(VerseRange $source): array;

    /** A page of the mushaf with its text; null when it is not there. */
    public function quranPage(int $page): ?QuranPage;

    /**
     * The passages these curriculum lessons read, for those that have one.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, VerseRange> by lesson
     */
    public function textSources(array $nodeIds): array;
}
