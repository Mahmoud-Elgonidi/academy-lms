<?php

namespace Modules\SharedKernel\Ai;

/** The model's answer as data, which model wrote it, and the tokens it took. */
final readonly class AiAnswer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{input?: int, output?: int, cache_read?: int, cache_write?: int}  $usage
     */
    public function __construct(
        public array $data,
        public string $model,
        public array $usage = [],
    ) {}
}
