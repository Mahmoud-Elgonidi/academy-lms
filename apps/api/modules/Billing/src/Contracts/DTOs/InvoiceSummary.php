<?php

namespace Modules\Billing\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * An invoice as its payer reads it before paying (docs/architecture/02,
 * section 27, J3): what it is for and what came off it, line by line, the
 * VAT, the total, what is left, the coupon on it and whether one can be
 * typed now (with why not, in the payer's words), and its signed pages
 * (paying, and the document to print).
 */
final readonly class InvoiceSummary
{
    /**
     * @param  list<array{kind: string, description: string, quantity: int, amount_minor: int}>  $lines
     * @param  ?array{code: string, personal: bool}  $coupon
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $status,              // issued | partly_paid | overdue | paid | void
        public string $currency,
        public array $lines,
        public int $subtotalMinor,
        public int $taxMinor,
        public int $taxRateBp,
        public bool $pricesIncludeTax,
        public int $totalMinor,
        public int $paidMinor,
        public int $outstandingMinor,
        public ?CarbonImmutable $dueAt,
        public ?array $coupon,
        public bool $couponOpen,
        public ?string $couponWhyNot,
        public string $payUrl,
        public string $documentUrl,
    ) {}

    /** @return array<string, mixed> the same shape wherever the family reads an invoice before paying */
    public function present(LocalTimePresenter $time): array
    {
        $money = fn (int $minor) => ['amount_minor' => $minor, 'amount' => Money::of($minor, $this->currency)->format()];
        $lines = [];
        foreach ($this->lines as $line) {
            $lines[] = [
                /** block (the plan's lessons), extra (extra lessons) or discount (the sibling discount, a coupon, the trial's offer: a negative amount) */
                'kind' => $line['kind'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'amount_minor' => $line['amount_minor'],
                'amount' => Money::of($line['amount_minor'], $this->currency)->format(),
            ];
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            /** issued, partly_paid, overdue, paid or void */
            'status' => $this->status,
            'currency' => $this->currency,
            'lines' => $lines,
            /** What the lines come to before VAT is added (with prices_include_tax, the VAT is inside it). */
            'subtotal' => $money($this->subtotalMinor),
            'vat' => [
                /** The VAT rate in percent (15 = 15%); 0 when the academy adds none. */
                'rate' => $this->taxRateBp / 100,
                /** The prices already hold the VAT (the Gulf's way); else it is added. */
                'included' => $this->pricesIncludeTax,
                'amount_minor' => $this->taxMinor,
                'amount' => Money::of($this->taxMinor, $this->currency)->format(),
            ],
            'total' => $money($this->totalMinor),
            'paid' => $money($this->paidMinor),
            'outstanding' => $money($this->outstandingMinor),
            'due_at' => $time->present($this->dueAt),
            /** The coupon on it (personal: the trial's offer, which stays on). */
            'coupon' => $this->coupon === null ? null : ['code' => $this->coupon['code'], 'personal' => $this->coupon['personal']],
            /** Whether a coupon can be typed or taken off now; why not in the payer's words when not. */
            'coupon_entry' => ['open' => $this->couponOpen, 'why_not' => $this->couponWhyNot],
            /** The academy's signed pay page for it (no sign-in needed). */
            'pay_url' => $this->payUrl,
            /** The invoice to read and print. */
            'document_url' => $this->documentUrl,
        ];
    }
}
