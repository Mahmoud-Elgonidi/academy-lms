<?php

namespace Modules\SharedKernel\Text;

/**
 * Arabic as it is compared, never as it is shown: the same word written
 * with or without its marks, in any of its alef forms, reads as one. Used
 * to line up what a recording said with the words it was meant to say, and
 * to find words in a model's answer. The text itself is never changed where
 * it is kept; this works on copies.
 *
 * The same rules as tools/course/listen.mjs. A dagger alef goes with the
 * marks, as speech to text writes the plain spelling.
 */
final class ArabicText
{
    /** Marks that do not change the letters: harakat, tanwin, shadda, sukun, dagger alef, small high signs, tatweel. */
    private const MARKS = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u';

    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(string $text): string
    {
        $text = (string) preg_replace(self::MARKS, '', $text);
        $text = strtr($text, self::LETTERS);
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        // "و الشمس" heard apart is one word, as written.
        $text = (string) preg_replace('/(^|\s)و\s+/u', '$1و', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        $text = self::normalize($text);

        return $text === '' ? [] : explode(' ', $text);
    }
}
