<?php

namespace Modules\Billing\Domain;

/**
 * What one block of a plan buys (docs/architecture/02, "التجديد بالبلوك"):
 * the minutes, how long they stay valid, and when to send the next invoice.
 */
final readonly class BlockTerms
{
    public function __construct(
        public int $sessionMinutes,
        public int $sessionsPerBlock,
        public int $sessionsPerWeek,
        public int $validityDays,
        public int $renewAtSessions,
    ) {}

    public function minutes(): int
    {
        return $this->sessionMinutes * $this->sessionsPerBlock;
    }

    public function lowBalanceMinutes(): int
    {
        return $this->sessionMinutes * $this->renewAtSessions;
    }

    /** The weeks the block needs plus two weeks' grace: 8 sessions at 2 a week → 6 weeks. */
    public static function defaultValidityDays(int $sessionsPerBlock, int $sessionsPerWeek): int
    {
        return ((int) ceil($sessionsPerBlock / max(1, $sessionsPerWeek)) + 2) * 7;
    }
}
