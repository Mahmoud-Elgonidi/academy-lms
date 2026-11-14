<?php

namespace Modules\Platform\Payments;

/**
 * What a charge on the saved card came to: paid, declined (with why), pending
 * (Stripe tells us later), or unknown (no clear answer: the same request, with
 * the same Idempotency-Key, is sent again later, never a new one).
 */
final readonly class StripeCharge
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const UNKNOWN = 'unknown';

    public function __construct(public string $status, public ?string $ref = null, public ?CardDecline $decline = null) {}

    public static function failed(CardDecline $decline, ?string $ref = null): self
    {
        return new self(self::FAILED, $ref, $decline);
    }

    public static function unknown(): self
    {
        return new self(self::UNKNOWN);
    }
}
