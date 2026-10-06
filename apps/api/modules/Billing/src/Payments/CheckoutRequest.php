<?php

namespace Modules\Billing\Payments;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Money\Money;

/** What a gateway needs to open its page for one checkout. */
final readonly class CheckoutRequest
{
    public function __construct(
        public string $checkoutId,
        public Money $amount,
        public string $description,
        public string $invoiceNumber,
        public string $returnUrl,
        public string $cancelUrl,
        public string $webhookUrl,
        public CarbonImmutable $expiresAt,
        public string $locale,
        public ?string $payerName = null,
        public ?string $payerEmail = null,
        public ?string $payerPhone = null,
    ) {}
}
