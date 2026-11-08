<?php

namespace Modules\SharedKernel\Plans;

/**
 * What the platform meters for each academy in its ledger (docs/architecture/02,
 * section 11): video minutes on our own video, and the AI helpers' credits,
 * weighted by helper. Each meter has a monthly allowance from the plan's
 * monthly limit and the lots bought or granted on top of it.
 */
enum Meter: string
{
    case VideoMinutes = 'video_minutes';
    case AiCredits = 'ai_credits';

    /** The plan's monthly limit this meter's allowance comes from. */
    public function limit(): Limit
    {
        return match ($this) {
            self::VideoMinutes => Limit::VideoMinutes,
            self::AiCredits => Limit::AiUses,
        };
    }

    public static function fromLimit(Limit $limit): ?self
    {
        return match ($limit) {
            Limit::VideoMinutes => self::VideoMinutes,
            Limit::AiUses => self::AiCredits,
            default => null,
        };
    }

    public function label(?string $locale = null): string
    {
        return __('shared::plans.meters.'.$this->value, [], $locale);
    }
}
