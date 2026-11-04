<?php

namespace Modules\Billing\Payments\Cards;

use Modules\SharedKernel\Money\Money;

/** What a gateway needs to charge a saved card once, with no one at the page (a merchant-initiated charge). */
final readonly class SavedCharge
{
    /** @param  array<string, string>  $tokens  the card's references at the gateway (PaymentMethod::tokens) */
    public function __construct(
        public string $checkoutId,
        public Money $amount,
        public array $tokens,
        public string $description,
        public string $invoiceNumber,
        public string $webhookUrl,
        public string $returnUrl,
    ) {}
}
