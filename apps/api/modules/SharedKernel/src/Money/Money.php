<?php

namespace Modules\SharedKernel\Money;

use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

/**
 * An amount in a currency's minor units (docs/architecture/03, "قواعد الفلوس").
 * The Kuwaiti, Bahraini, Omani and Jordanian dinars have three decimals, so
 * "minor" is a fils, not a hundredth; brick/money knows each currency's scale.
 * Never a float.
 */
final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Bad currency [{$currency}].");
        }
    }

    public static function of(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    /** "450" or "12.5" as typed in a form, in this currency's minor units. */
    public static function parse(string|int|float $amount, string $currency): self
    {
        $money = BrickMoney::of((string) $amount, strtoupper($currency), roundingMode: RoundingMode::HalfUp);

        return new self($money->getMinorAmount()->toInt(), strtoupper($currency));
    }

    public static function decimals(string $currency): int
    {
        return Currency::of(strtoupper($currency))->getDefaultFractionDigits();
    }

    public function plus(self $other): self
    {
        $this->sameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->sameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    /** The amount as a plain decimal string, for form inputs: "450.00", "12.500". */
    public function decimal(): string
    {
        return (string) BrickMoney::ofMinor($this->minor, $this->currency)->getAmount();
    }

    /**
     * "4,500 ر.س." in Arabic (Latin digits, like the rest of the back office),
     * "SAR 4,500" in English. In Arabic the amount is a bidi isolate, so a
     * Latin symbol (US$, £) keeps its place in right-to-left text.
     */
    public function format(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $arabic = str_starts_with($locale, 'ar');
        // The direction marks ICU puts around it go by character, not byte: trim() would cut a leading "€" (its first byte is theirs too).
        $text = (string) preg_replace('/^[\x{200E}\x{200F}\s]+|[\x{200E}\x{200F}\s]+$/u', '', BrickMoney::ofMinor($this->minor, $this->currency)->formatToLocale($arabic ? 'ar-u-nu-latn' : $locale, hideFractionIfWhole: true));

        return $arabic ? "\u{2068}{$text}\u{2069}" : $text;
    }

    private function sameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot mix {$this->currency} and {$other->currency}.");
        }
    }
}
