<?php

namespace Modules\SharedKernel\Logging;

/**
 * What a log line must never carry in the clear (docs/architecture/03,
 * section 11): people's emails and phone numbers, access tokens, a signed
 * link's signature, and the values of fields named like secrets. The line
 * stays readable: an email keeps its domain, a number its last two digits.
 */
final class Redactor
{
    public const MASK = '[redacted]';

    /** Fields whose value is a secret wherever they appear (JSON or key=value). */
    private const SECRET_FIELDS = 'password|password_confirmation|current_password|token|plain_text_token|access_token|refresh_token|id_token|two_factor_token|'
        .'discovery_token|secret|client_secret|api_key|apikey|authorization|code|otp|recovery_code|recovery_codes|app_authentication_secret|signature|bind|ticket';

    public static function text(string $line): string
    {
        return (string) preg_replace_callback_array([
            // "password":"…", "token": "…" in the JSON of a context.
            '/("(?:'.self::SECRET_FIELDS.')"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i' => fn (array $m) => $m[1].'"'.self::MASK.'"',
            // ?signature=…&token=… in an address.
            '/([?&](?:'.self::SECRET_FIELDS.')=)[^&\s"\'<>]+/i' => fn (array $m) => $m[1].self::MASK,
            // Authorization: Bearer …, and Sanctum's "id|secret".
            '/\b(Bearer)\s+[A-Za-z0-9._~+\/=|-]+/i' => fn (array $m) => $m[1].' '.self::MASK,
            '/\b\d+\|[A-Za-z0-9]{40,}\b/' => fn () => self::MASK,
            // Emails: the domain stays.
            '/[A-Za-z0-9._%+-]+@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,})/' => fn (array $m) => '***@'.$m[1],
            // Phone numbers: +E.164, or a local mobile number (05…, 01…); the last two digits stay.
            '/(?<![\w+])\+\d{8,15}\b|(?<![\w.:-])0[15]\d{8,9}\b/' => fn (array $m) => '***'.substr($m[0], -2),
        ], $line);
    }
}
