<?php

namespace Modules\SharedKernel\Ai;

/** What a recording said, its words with times, how long it was, and who heard it. */
final readonly class Transcript
{
    /** @param  list<HeardWord>  $words */
    public function __construct(
        public string $text,
        public array $words,
        public float $seconds,
        public string $model,
    ) {}
}
