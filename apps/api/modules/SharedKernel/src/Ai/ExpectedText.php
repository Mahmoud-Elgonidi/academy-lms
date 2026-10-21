<?php

namespace Modules\SharedKernel\Ai;

/**
 * What a recording was meant to say, as the platform keeps it: verses (the
 * Quran's, by surah and number) or one run of words, each with its words in
 * order. A recitation engine compares what it hears with this; speech to
 * text is never told it.
 */
final readonly class ExpectedText
{
    /** @param  list<array{surah: ?int, verse: ?int, words: list<string>}>  $verses */
    public function __construct(public array $verses) {}

    /** @return list<string> every word, in order */
    public function words(): array
    {
        return array_merge(...array_map(fn (array $v) => $v['words'], $this->verses ?: [['words' => []]]));
    }
}
