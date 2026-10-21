<?php

namespace Modules\Platform\Ai;

use Modules\SharedKernel\Health\ServiceProblem;

/** A provider's models for this key (id => what the screen shows), or why there are none. */
final readonly class ModelList
{
    /** @param  array<string, string>  $options */
    public function __construct(
        public array $options,
        public ?ServiceProblem $problem = null,
        public string $code = '',
        public int $at = 0,
    ) {}

    /** @param  array{options?: array<string, string>, problem?: ?string, code?: string, at?: int}  $kept */
    public static function from(array $kept): self
    {
        return new self((array) ($kept['options'] ?? []), ServiceProblem::tryFrom((string) ($kept['problem'] ?? '')), (string) ($kept['code'] ?? ''), (int) ($kept['at'] ?? 0));
    }

    /** @return array{options: array<string, string>, problem: ?string, code: string, at: int} plain, as the cache keeps it */
    public function toArray(): array
    {
        return ['options' => $this->options, 'problem' => $this->problem?->value, 'code' => $this->code, 'at' => $this->at];
    }
}
