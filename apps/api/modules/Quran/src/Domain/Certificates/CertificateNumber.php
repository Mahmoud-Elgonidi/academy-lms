<?php

namespace Modules\Quran\Domain\Certificates;

use InvalidArgumentException;

/**
 * A certificate's number and verification code (docs/architecture/02,
 * section 26): the number reads like Q-2026-000123 (the year it was
 * issued, the academy's certificates in order); the code is random, from
 * letters and digits nobody misreads, written in groups of four, and read
 * back whatever its case or dashes.
 */
final class CertificateNumber
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';

    public const CODE_LENGTH = 16;

    public static function format(int $year, int $seq): string
    {
        if ($seq < 1 || $year < 2000 || $year > 9999) {
            throw new InvalidArgumentException('A certificate number needs a year and a positive sequence.');
        }

        return sprintf('Q-%d-%06d', $year, $seq);
    }

    /** @param  callable(int, int): int  $random  random_int */
    public static function code(callable $random): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[$random(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** XXXX-XXXX-XXXX-XXXX */
    public static function display(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    /** A code as typed (any case, spaces or dashes), or null when it cannot be one. */
    public static function normalize(string $typed): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $typed));

        return strlen($code) === self::CODE_LENGTH && strspn($code, self::ALPHABET) === self::CODE_LENGTH ? $code : null;
    }
}
