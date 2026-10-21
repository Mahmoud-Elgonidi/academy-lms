<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * A page of the Madinah mushaf as the platform keeps its text: the verses on
 * it as Tanzil writes them (perhaps the end of one surah and the start of the
 * next), its words counted through the page from 1, the surahs it holds, its
 * part, and the attribution the text asks to be shown with.
 */
final readonly class QuranPage
{
    /**
     * @param  list<array{surah: int, verse: int, text: string, first: int, last: int}>  $verses
     * @param  list<string>  $words
     * @param  list<array{number: int, name_ar: string, name_en: string}>  $surahs
     */
    public function __construct(
        public int $page,
        public int $juz,
        public array $verses,
        public array $words,
        public array $surahs,
        public string $attribution,
    ) {}

    /** "صفحة ١٢ · البقرة" (the page and the surahs on it, in Arabic). */
    public function title(): string
    {
        return 'صفحة '.Passage::arabicNumber($this->page).' · '.implode('، ', array_column($this->surahs, 'name_ar'));
    }
}
