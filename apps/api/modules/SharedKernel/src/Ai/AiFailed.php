<?php

namespace Modules\SharedKernel\Ai;

use RuntimeException;

/**
 * Why an AI helper gave nothing back. The screens say it in the reader's
 * words (shared::ai.failed.{reason}); the API answers it as a problem
 * (ProblemDetails): off 403, limit 429, busy/unavailable/not_configured 503,
 * the rest 422.
 */
final class AiFailed extends RuntimeException
{
    public const REASONS = ['off', 'limit', 'busy', 'not_configured', 'unavailable', 'refused', 'too_long', 'unreadable', 'unheard'];

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail !== '' ? $detail : __('shared::ai.failed.'.$reason));
    }

    public function status(): int
    {
        return match ($this->reason) {
            'off' => 403,
            'limit' => 429,
            'busy', 'unavailable', 'not_configured' => 503,
            default => 422,
        };
    }

    public function type(): string
    {
        return match ($this->reason) {
            'off' => 'ai-off',
            'limit' => 'ai-limit',
            'busy', 'unavailable', 'not_configured' => 'ai-unavailable',
            'unheard' => 'ai-unheard',
            default => 'ai-no-answer',
        };
    }

    /** What the reader is told, never the provider's own message. */
    public function sayable(): string
    {
        return __('shared::ai.failed.'.$this->reason);
    }
}
