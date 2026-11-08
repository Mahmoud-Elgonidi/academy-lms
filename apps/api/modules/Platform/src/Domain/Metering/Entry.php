<?php

namespace Modules\Platform\Domain\Metering;

/** One line of a meter's ledger, as the statement reads it. */
final readonly class Entry
{
    public function __construct(
        public string $kind,
        public int $amount,
        public ?string $lotId,
        public bool $overage = false,
    ) {}

    /** It moves the balance: not a hold or its release, and not a use counted while the plan had no limit. */
    public function counts(): bool
    {
        if (in_array($this->kind, [Ledger::RESERVE, Ledger::RELEASE], true)) {
            return false;
        }

        return $this->lotId !== null || $this->overage;
    }
}
