<?php

namespace Modules\Classroom\Video\LiveKit;

use RuntimeException;

/** The small part of JWT (HS256) LiveKit needs: access tokens out, webhook signatures in. */
final class Jwt
{
    /** @param  array<string, mixed>  $claims */
    public static function encode(array $claims, string $secret): string
    {
        $parts = [self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), self::b64((string) json_encode($claims, JSON_UNESCAPED_SLASHES))];

        return implode('.', [...$parts, self::b64(hash_hmac('sha256', implode('.', $parts), $secret, true))]);
    }

    /** @return array<string, mixed> the claims, when the signature and the time are right */
    public static function decode(string $jwt, string $secret, int $leewaySeconds = 60): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token.');
        }
        [$header, $payload, $signature] = $parts;
        $alg = json_decode(self::unb64($header), true)['alg'] ?? null;
        if ($alg !== 'HS256' || ! hash_equals(self::b64(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), $signature)) {
            throw new RuntimeException('Bad signature.');
        }
        $claims = json_decode(self::unb64($payload), true);
        if (! is_array($claims)) {
            throw new RuntimeException('Malformed claims.');
        }
        $now = time();
        if ((isset($claims['exp']) && $now > $claims['exp'] + $leewaySeconds) || (isset($claims['nbf']) && $now < $claims['nbf'] - $leewaySeconds)) {
            throw new RuntimeException('Token not valid now.');
        }

        return $claims;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }
}
