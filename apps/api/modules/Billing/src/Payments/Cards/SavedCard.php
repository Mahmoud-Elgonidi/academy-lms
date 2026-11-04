<?php

namespace Modules\Billing\Payments\Cards;

/**
 * A card the gateway kept for the payer, as the gateway itself said after a
 * payment they asked to save it on (never what the browser said): its own
 * references to it (tokens), and what may be shown.
 */
final readonly class SavedCard
{
    /** @param  array<string, string>  $tokens  Moyasar: token; Tap: card, customer, agreement; Stripe: customer, payment_method; demo: token */
    public function __construct(
        public array $tokens,
        public string $brand,
        public string $last4,
        public int $expMonth,
        public int $expYear,
    ) {}

    /** Whether the gateway gave everything a card needs to be charged again and shown. */
    public function complete(): bool
    {
        return $this->tokens !== [] && ! in_array('', $this->tokens, true)
            && preg_match('/^\d{4}$/', $this->last4) === 1 && $this->expMonth >= 1 && $this->expMonth <= 12 && $this->expYear >= 2000;
    }
}
