<?php

namespace Modules\Billing\Payments\Cards;

/**
 * What a gateway answered a charge on a saved card. Only "paid" leads to
 * money being recorded, and only through SettleCheckout asking the gateway
 * again; "pending" and "unknown" are asked again later and never charged
 * twice; "action_required" (the bank wants the payer, 3-D Secure) is never
 * pushed through: the payer gets the pay link.
 */
final readonly class ChargeResult
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const ACTION_REQUIRED = 'action_required';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $status,
        public ?string $ref = null,
        public ?CardFailure $failure = null,
    ) {}

    public static function failed(CardFailure $why, ?string $ref = null): self
    {
        return new self($why === CardFailure::AuthenticationRequired ? self::ACTION_REQUIRED : self::FAILED, $ref, $why);
    }

    /** No answer we can trust (the network, the gateway's own error): asked again later, never charged again. */
    public static function unknown(?string $ref = null): self
    {
        return new self(self::UNKNOWN, $ref);
    }
}
