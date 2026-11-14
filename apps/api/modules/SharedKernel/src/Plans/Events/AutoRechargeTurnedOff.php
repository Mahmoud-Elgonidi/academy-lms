<?php

namespace Modules\SharedKernel\Plans\Events;

/** Automatic recharge of a meter turned itself off ($reason: declined, no_card, no_price), so the staff can set it again. */
final readonly class AutoRechargeTurnedOff
{
    public function __construct(
        public string $academyId,
        public string $meter,
        public string $reason,
    ) {}
}
