<?php

namespace Modules\Billing\Zatca;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\SharedKernel\Money\Money;

/**
 * The QR code on a Saudi simplified tax invoice (ZATCA, phase 1): five
 * fields in TLV (a tag byte, a length byte, the UTF-8 value), base64 of
 * the whole. 1: the seller's name, 2: their VAT number, 3: when it was
 * issued (UTC, ISO 8601), 4: the total with VAT, 5: the VAT.
 */
final class ZatcaQr
{
    public static function encode(string $sellerName, string $vatNumber, CarbonImmutable $issuedAt, Money $total, Money $vat): string
    {
        $fields = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->utc()->format('Y-m-d\TH:i:s\Z'),
            4 => $total->decimal(),
            5 => $vat->decimal(),
        ];
        $tlv = '';
        foreach ($fields as $tag => $value) {
            $length = strlen($value);
            if ($length === 0 || $length > 255) {
                throw new InvalidArgumentException("ZATCA QR field {$tag} must be 1 to 255 bytes.");
            }
            $tlv .= chr($tag).chr($length).$value;
        }

        return base64_encode($tlv);
    }

    /** @return array<int, string> tag => value, to read a code back */
    public static function decode(string $base64): array
    {
        $tlv = (string) base64_decode($base64, true);
        $fields = [];
        for ($i = 0; $i + 2 <= strlen($tlv);) {
            $tag = ord($tlv[$i]);
            $length = ord($tlv[$i + 1]);
            $fields[$tag] = substr($tlv, $i + 2, $length);
            $i += 2 + $length;
        }

        return $fields;
    }

    /** A Saudi VAT registration number: 15 digits, starting and ending with 3. */
    public static function validVatNumber(?string $number): bool
    {
        return is_string($number) && (bool) preg_match('/^3\d{13}3$/', $number);
    }
}
