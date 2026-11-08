<?php

namespace Modules\SharedKernel\Ai;

use RuntimeException;

/**
 * Why an AI helper gave nothing back. The screens say it in the reader's
 * words (shared::ai.failed.{reason}); the API answers it as a problem
 * (ProblemDetails): off 403, limit and quota (the academy's AI credits,
 * the month's allowance and the bought balance, are used up; with the
 * credits the request needed when it is known) 429,
 * busy/unavailable/not_configured 503, the rest 422.
 */
final class AiFailed extends RuntimeException
{
    public const REASONS = ['off', 'limit', 'quota', 'busy', 'not_configured', 'unavailable', 'refused', 'too_long', 'unreadable', 'unheard'];

    /** @param  ?int  $needed  for quota: the credits this request needed */
    public function __construct(public readonly string $reason, string $detail = '', public readonly ?int $needed = null)
    {
        parent::__construct($detail !== '' ? $detail : self::say($reason, $needed));
    }

    public function status(): int
    {
        return match ($this->reason) {
            'off' => 403,
            'limit', 'quota' => 429,
            'busy', 'unavailable', 'not_configured' => 503,
            default => 422,
        };
    }

    public function type(): string
    {
        return match ($this->reason) {
            'off' => 'ai-off',
            'limit' => 'ai-limit',
            'quota' => 'ai-quota',
            'busy', 'unavailable', 'not_configured' => 'ai-unavailable',
            'unheard' => 'ai-unheard',
            default => 'ai-no-answer',
        };
    }

    /** What the reader is told, never the provider's own message. */
    public function sayable(): string
    {
        return self::say($this->reason, $this->needed);
    }

    private static function say(string $reason, ?int $needed): string
    {
        return $reason === 'quota' && $needed !== null && $needed > 0
            ? trans_choice('shared::ai.failed.quota_needed', $needed, ['n' => $needed])
            : __('shared::ai.failed.'.$reason);
    }
}
