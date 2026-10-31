<?php

namespace Modules\Quran\Contracts\DTOs;

/**
 * A passage of the Quran as it is read: its words in order, exactly as kept
 * (with their marks), each verse's words, a title ("الفاتحة ١–٧"), and the
 * attribution its source asks to be shown with it. Words are counted from 1,
 * as a model is shown them.
 */
final readonly class QuranPassage
{
    /**
     * @param  list<string>  $words
     * @param  list<array{surah: int, verse: int, text: string, first: int, last: int}>  $verses  each verse and its words' numbers
     */
    public function __construct(
        public VerseRange $source,
        public string $title,
        public array $words,
        public array $verses,
        public string $attribution,
    ) {}

    /** Words $from to $to (from 1), as kept; empty when outside the passage. */
    public function quote(int $from, int $to): string
    {
        if ($from < 1 || $to < $from || $to > count($this->words)) {
            return '';
        }

        return implode(' ', array_slice($this->words, $from - 1, $to - $from + 1));
    }

    /** The whole text, each verse followed by its number. */
    public function text(): string
    {
        return implode(' ', array_map(fn (array $v) => $v['text'].' ﴿'.self::arabicNumber($v['verse']).'﴾', $this->verses));
    }

    public static function arabicNumber(int $n): string
    {
        return strtr((string) $n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }
}
