<?php

namespace Modules\Learning\Digest;

/**
 * Whether a written weekly note may go out (pure): one paragraph of plain
 * words, short, about the child as {child}, saying no number the week's
 * facts do not hold, and no link, address, email or phone number. A note
 * that fails goes out in the facts' own words instead (FactsText).
 */
final class WordingCheck
{
    public const MIN = 20;

    public const MAX = 600;

    /**
     * The note as it may go out (whitespace made one paragraph), or null when it may not.
     *
     * @param  list<int>  $numbers  WeekFacts::numbers()
     */
    public static function clean(mixed $text, array $numbers): ?string
    {
        if (! is_string($text)) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);
        if ($length < self::MIN || $length > self::MAX) {
            return null;
        }
        $plain = self::latinDigits($text);
        $outside = preg_match('~https?:|www\.|[\w.-]+\.(com|net|org|io|me|app|link|ly)\b|@|<|>~iu', $plain) === 1
            || preg_match('/\d[\d\s().+-]{6,}\d/', $plain) === 1;
        // Placeholders other than the child's would reach the guardian as they are.
        $braces = preg_replace('/\{child\}/', '', $plain);
        if ($outside || str_contains((string) $braces, '{') || str_contains((string) $braces, '}')) {
            return null;
        }
        preg_match_all('/\d+/', $plain, $m);
        foreach ($m[0] as $n) {
            if (! in_array((int) $n, $numbers, true)) {
                return null;
            }
        }

        return $text;
    }

    /** Arabic and Persian digits as Latin ones. */
    public static function latinDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
