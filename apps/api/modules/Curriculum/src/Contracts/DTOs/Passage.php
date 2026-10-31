<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * A lesson's text as it is read: its words in order, exactly as kept.
 * Words are counted from 1, as a model is shown them.
 */
final readonly class Passage
{
    /** @param  list<string>  $words */
    public function __construct(
        public TextSource $source,
        public array $words,
    ) {}

    /** Words $from to $to (from 1), as kept; empty when outside the passage. */
    public function quote(int $from, int $to): string
    {
        if ($from < 1 || $to < $from || $to > count($this->words)) {
            return '';
        }

        return implode(' ', array_slice($this->words, $from - 1, $to - $from + 1));
    }

    /** The whole text. */
    public function text(): string
    {
        return implode(' ', $this->words);
    }
}
