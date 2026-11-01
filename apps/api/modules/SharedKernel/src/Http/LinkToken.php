<?php

namespace Modules\SharedKernel\Http;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A short token for a personal link that works without signing in (a
 * family's "pick a time" or "change the trial"): it names one record for
 * one purpose in one academy until a moment, signed with APP_KEY. Whoever
 * holds the link may do what it is for, and nothing else; a token for
 * another purpose, another academy or past its time reads as nothing.
 */
final class LinkToken
{
    public static function issue(string $purpose, string $id, CarbonImmutable $until): string
    {
        $payload = $id.'.'.$until->getTimestamp();

        return self::encode($payload).'.'.self::encode(self::signature($purpose, $payload));
    }

    /** The record's id, when the token is this purpose's, this academy's and still good; else null. */
    public static function read(string $purpose, ?string $token): ?string
    {
        $signed = self::signed($purpose, $token);

        return $signed !== null && $signed[1] > now()->getTimestamp() ? $signed[0] : null;
    }

    /**
     * Whether the token is this purpose's and this academy's but its time has
     * passed: a page may then say the link ended (410) rather than "not
     * found". A forged token, or another academy's or purpose's, is not expired.
     */
    public static function expired(string $purpose, ?string $token): bool
    {
        $signed = self::signed($purpose, $token);

        return $signed !== null && $signed[1] <= now()->getTimestamp();
    }

    /** @return array{0: string, 1: int}|null the record's id and the moment it is good until, when signed for this purpose here */
    private static function signed(string $purpose, ?string $token): ?array
    {
        if ($token === null || strlen($token) > 300 || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        $payload = self::decode($payload);
        $signature = self::decode($signature);
        if ($payload === null || $signature === null || ! hash_equals(self::signature($purpose, $payload), $signature)) {
            return null;
        }
        $dot = strrpos($payload, '.');
        if ($dot === false) {
            return null;
        }

        return [substr($payload, 0, $dot), (int) substr($payload, $dot + 1)];
    }

    private static function signature(string $purpose, string $payload): string
    {
        $academy = (string) app(TenantContext::class)->id();

        return substr(hash_hmac('sha256', $purpose.'|'.$academy.'|'.$payload, 'link-token|'.config('app.key'), true), 0, 18);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+$/', $text) !== 1) {
            return null;
        }
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
