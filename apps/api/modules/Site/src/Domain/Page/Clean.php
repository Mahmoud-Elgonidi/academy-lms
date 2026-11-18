<?php

namespace Modules\Site\Domain\Page;

/** Small cleaners the page's documents share: words, numbers, addresses, links (pure). */
final class Clean
{
    public const LOCALES = ['ar', 'en'];

    /** One line or a few, without what cannot be read (controls, surrounding spaces): SiteContent's rule. */
    public static function text(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = (string) preg_replace('/[^\P{C}\n]/u', '', str_replace(["\r\n", "\r"], "\n", $text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** "+20 100 123-4567" → "+201001234567"; "00…" → "+…". */
    public static function phone(mixed $value): string
    {
        $phone = (string) preg_replace('/[\s\-().]/', '', self::text($value));

        return str_starts_with($phone, '00') ? '+'.substr($phone, 2) : $phone;
    }

    public static function isPhone(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }

    public static function isEmail(string $email): bool
    {
        return strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function uuid(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1 ? strtolower($value) : null;
    }

    public static function length(string $text): int
    {
        return mb_strlen($text);
    }

    /** An https address with a host, at most 500 characters. */
    public static function isHttps(string $url): bool
    {
        if (strlen($url) > 500 || ! str_starts_with(strtolower($url), 'https://') || preg_match('/[\s<>"\\\\]/', $url) === 1) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' && str_contains($host, '.');
    }

    /**
     * Where a button or a link in text may go: https, mailto:, tel:+…, or one
     * of the portal's own pages (/ar/…, /en/…). Never javascript:, data: or
     * plain http.
     */
    public static function isLinkUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 500 || preg_match('/[\s<>"\\\\]/', $url) === 1) {
            return false;
        }
        $lower = strtolower($url);
        if (str_starts_with($lower, 'https://')) {
            return self::isHttps($url);
        }
        if (str_starts_with($lower, 'mailto:')) {
            return self::isEmail(substr($url, 7));
        }
        if (str_starts_with($lower, 'tel:')) {
            return self::isPhone(substr($url, 4));
        }

        return preg_match('#^/(ar|en)(/[A-Za-z0-9\-._~%/]*)?([?\#][A-Za-z0-9\-._~%/=&]*)?$#', $url) === 1;
    }

    /** "https://www.youtube.com/watch?v=abc…", "youtu.be/abc…" or the id itself → the 11-character id; null otherwise. */
    public static function youtube(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('~^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~', $value, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** "https://vimeo.com/123456789" or the number → the id; null otherwise. */
    public static function vimeo(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^[0-9]{6,12}$/', $value) === 1) {
            return $value;
        }
        if (preg_match('~^https://(?:www\.|player\.)?vimeo\.com/(?:video/)?([0-9]{6,12})(?:[?#/].*)?$~', $value, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
