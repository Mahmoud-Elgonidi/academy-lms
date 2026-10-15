<?php

namespace Modules\SharedKernel\Ai;

/** One word as heard, from $start to $end seconds into the recording. */
final readonly class HeardWord
{
    public function __construct(
        public string $text,
        public float $start,
        public float $end,
    ) {}
}
