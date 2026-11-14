<?php

namespace Modules\SharedKernel\Plans\Events;

/** An automatic recharge was declined (once per attempt): $reason in Platform's words, $retries whether it is tried again a day later. */
final readonly class RechargeFailed
{
    public function __construct(
        public string $academyId,
        public string $meter,
        public string $reason,
        public bool $retries,
        public string $purchaseId,
    ) {}
}
