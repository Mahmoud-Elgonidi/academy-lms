<?php

namespace Modules\SharedKernel\Ai;

/**
 * One request to the language model: a system prompt that stays the same
 * between requests (it is cached), the request itself, the answer's JSON
 * schema, how hard to think (low, medium, high), and what the demo driver
 * answers instead (the demo academy and the tests never reach the model).
 */
final readonly class AiPrompt
{
    public const TIERS = ['fast', 'best'];

    public const EFFORTS = ['low', 'medium', 'high'];

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $demo
     */
    public function __construct(
        public string $system,
        public string $user,
        public array $schema,
        public string $tier = 'fast',
        public string $effort = 'low',
        public int $maxTokens = 8000,
        public array $demo = [],
    ) {}
}
