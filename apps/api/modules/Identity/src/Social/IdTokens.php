<?php

namespace Modules\Identity\Social;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks an ID token from Google or Apple without their SDKs: an RS256
 * signature by one of the provider's published keys (fetched once an hour,
 * again at once for a key not seen yet), the issuer, one of our clients as
 * the audience, its times, and the nonce the sign-in started with. Anything
 * else is refused the same way (InvalidIdToken), so a caller learns nothing
 * about why.
 */
final class IdTokens
{
    /**
     * @param  list<string>  $audiences  the clients the token may be for
     * @param  ?string  $nonce  what the sign-in started with; a token carrying its SHA-256 (as Apple's apps send it) matches too
     */
    public function verify(Provider $provider, string $token, array $audiences, ?string $nonce = null): SocialProfile
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $audiences === []) {
            throw new InvalidIdToken('malformed');
        }
        [$head, $body, $signature] = $parts;
        $header = json_decode(self::decode($head), true);
        $claims = json_decode(self::decode($body), true);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw new InvalidIdToken('malformed');
        }

        $key = $this->key($provider, $header['kid']) ?? throw new InvalidIdToken('unknown key');
        if (openssl_verify("{$head}.{$body}", self::decode($signature), $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidIdToken('signature');
        }

        $now = CarbonImmutable::now()->getTimestamp();
        $leeway = (int) config('identity.social.leeway_seconds', 60);
        $aud = (array) ($claims['aud'] ?? []);
        $checks = [
            'issuer' => in_array($claims['iss'] ?? null, $provider->issuers(), true),
            'audience' => array_intersect($aud, $audiences) !== [],
            'expired' => is_numeric($claims['exp'] ?? null) && (int) $claims['exp'] + $leeway >= $now,
            'issued' => ! is_numeric($claims['iat'] ?? null) || (int) $claims['iat'] - $leeway <= $now,
            'subject' => is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 255,
            'nonce' => $nonce === null || in_array($claims['nonce'] ?? null, [$nonce, hash('sha256', $nonce)], true),
        ];
        if ($failed = array_search(false, $checks, true)) {
            throw new InvalidIdToken($failed);
        }

        $email = is_string($claims['email'] ?? null) ? $claims['email'] : null;

        return new SocialProfile(
            provider: $provider,
            subject: $claims['sub'],
            email: $email,
            // Google sends a boolean, Apple a boolean or "true".
            emailVerified: $email !== null && in_array($claims['email_verified'] ?? false, [true, 'true'], true),
        );
    }

    /** The provider's key with this id, as PEM; the keys are fetched again (once a minute at most) when it is new. */
    private function key(Provider $provider, string $kid): ?string
    {
        $cacheKey = "identity.social.jwks.{$provider->value}";
        $keys = Cache::remember($cacheKey, now()->addHour(), fn () => $this->fetch($provider));
        if (! isset($keys[$kid]) && Cache::add("{$cacheKey}.refreshed", true, now()->addMinute())) {
            $keys = $this->fetch($provider);
            Cache::put($cacheKey, $keys, now()->addHour());
        }

        return $keys[$kid] ?? null;
    }

    /** @return array<string, string> the provider's RSA keys by id, as PEM */
    private function fetch(Provider $provider): array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get((string) $provider->config()['jwks_url']);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
        $keys = [];
        foreach ((array) $response->json('keys', []) as $jwk) {
            if (($jwk['kty'] ?? null) === 'RSA' && is_string($jwk['kid'] ?? null) && is_string($jwk['n'] ?? null) && is_string($jwk['e'] ?? null)) {
                $keys[$jwk['kid']] = self::pem(self::decode($jwk['n']), self::decode($jwk['e']));
            }
        }

        return $keys;
    }

    /** An RSA public key (modulus and exponent) as a PEM SubjectPublicKeyInfo. */
    public static function pem(string $modulus, string $exponent): string
    {
        $integer = fn (string $bytes) => "\x02".self::length(strlen($bytes = (ord($bytes[0] ?? "\0") > 0x7F ? "\0".$bytes : $bytes))).$bytes;
        $rsaKey = $integer($modulus).$integer($exponent);
        $rsaKey = "\x30".self::length(strlen($rsaKey)).$rsaKey;
        $algorithm = (string) hex2bin('300d06092a864886f70d0101010500');   // rsaEncryption, NULL parameters
        $bits = "\x03".self::length(strlen($rsaKey) + 1)."\x00".$rsaKey;
        $info = "\x30".self::length(strlen($algorithm.$bits)).$algorithm.$bits;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($info), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
