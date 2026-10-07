<?php

namespace Modules\SharedKernel\Contact;

/**
 * Phone numbers as people type them, made E.164 ("+201012345678"): spaces,
 * dashes and brackets go, Arabic digits become Latin, "00" means "+", and a
 * local number (leading 0, or too short to carry a country) takes the
 * country code given. Null when it cannot be a number.
 */
final class Phone
{
    public static function normalize(?string $raw, ?string $countryCode = null): ?string
    {
        $raw = trim(strtr((string) $raw, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]));
        if ($raw === '') {
            return null;
        }
        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        $code = $countryCode === null ? null : (preg_replace('/\D/', '', $countryCode) ?: null);

        $number = match (true) {
            $plus => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            $code !== null && str_starts_with($digits, '0') => $code.substr($digits, 1),
            $code !== null && strlen($digits) <= 10 => $code.$digits,
            default => $digits,
        };

        return self::isE164('+'.$number) ? '+'.$number : null;
    }

    public static function isE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }
}
