<?php

namespace Modules\Messaging\Domain;

/**
 * Hides the ways to reach someone outside the platform from a message
 * before anyone reads it (docs/architecture/02, section 17): phone numbers
 * (Arabic digits too, spaced or dashed, eight digits or more; dates, times
 * and counting stay), emails (also "name (at) mail (dot) com"), links (the
 * academy's own stay) and @handles. Each one becomes MARK, and the kinds
 * hidden are listed. Naming an outside app (WhatsApp, Telegram, …) is only
 * flagged for the academy, never hidden. Pure: text in, text out.
 *
 * Numbers spelled out in words are not caught: the oversight screen and the
 * flags are there for what slips through.
 */
final class ContactMasker
{
    public const MARK = '[•••]';

    private const DIGIT = '[0-9٠-٩۰-۹]';

    private const MAIL_HOSTS = 'gmail|hotmail|yahoo|outlook|icloud|live|proton|protonmail|yandex';

    private const TLDS = 'com|net|org|info|biz|io|me|ly|co|us|app|link|gg|xyz|tv|to|site|online|store|page|dev|ai|chat|live|sa|eg|ae|kw|qa|bh|om|jo|ma|tn|dz|iq|lb|uk|de|fr|ca|au|in|pk|tr|ru';

    private const APPS = 'whats\s?app|whatsapp|wa\.me|telegram|instagram|insta|snapchat|facebook|messenger|tiktok|skype|discord|zoom|imo'
        .'|واتس\s?اب|واتساب|واتس|وتس|تليجرام|تلجرام|تيليجرام|تلغرام|انستجرام|انستغرام|انستا|سناب\s?شات|سناب|فيس\s?بوك|فيسبوك|فيس|ماسنجر|تيك\s?توك|سكايب|ديسكورد|زووم|ايمو';

    /** @param  list<string>  $allowedHosts  hosts whose links stay (the academy's own), lower case */
    public function __construct(private readonly array $allowedHosts = []) {}

    public function mask(string $text): Masked
    {
        $typed = $text;
        $hidden = [];
        $hide = function (string $kind) use (&$hidden): string {
            $hidden[$kind] = true;

            return self::MARK;
        };

        // Emails first: they hold dots and an @ the other patterns would split.
        $at = '(?:@|＠|[\(\[\{]\s*(?:at|ات)\s*[\)\]\}]|\s+at\s+(?=(?:'.self::MAIL_HOSTS.')\b))';
        $dot = '(?:\.|[\(\[\{]\s*(?:dot|دوت)\s*[\)\]\}]|\s+dot\s+)';
        $text = (string) preg_replace_callback('/[\p{L}\p{N}._%+\-]+\s*'.$at.'\s*[\p{L}\p{N}\-]+(?:\s*'.$dot.'\s*[\p{L}\p{N}\-]+)+/iu', fn () => $hide('email'), $text);

        // Links: with a scheme, www., or a name under a known ending (wa.me, t.me, bit.ly, zoom.us …).
        $link = '/(?:\b(?:https?|ftp):\/\/[^\s<>"\']+|\bwww\.[^\s<>"\']+|(?<![\p{L}\p{N}@.\-])(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+(?:'.self::TLDS.')\b(?:\/[^\s<>"\']*)?)/iu';
        $text = (string) preg_replace_callback($link, function (array $m) use ($hide) {
            [$url, $tail] = self::trimTail($m[0]);

            return ($this->allowed($url) ? $url : $hide('link')).$tail;
        }, $text);

        // Handles: @name (an email is gone by now).
        $text = (string) preg_replace_callback('/(?<![\p{L}\p{N}_.])[@＠][A-Za-z0-9_.]{3,30}/u', fn () => $hide('handle'), $text);

        // Phone numbers: eight digits or more, however they are spaced, Arabic digits too.
        $d = self::DIGIT;
        $text = (string) preg_replace_callback('/\(?(?:\+|00)?'.$d.'(?:[\s\-.()\/:]{0,3}'.$d.'){7,}/u', function (array $m) use ($hide) {
            [$run, $tail] = self::trimTail($m[0]);

            return (self::isPhone($run) ? $hide('phone') : $run).$tail;
        }, $text);

        $flags = preg_match('/(?<![\p{L}\p{N}])(?:و|ف|ب|ل|ع|ال|بال|عال|وال|فال|لل|عل)?(?:'.self::APPS.')(?![\p{L}\p{N}])/iu', $typed) === 1 ? ['outside_app'] : [];

        return new Masked($text, array_keys($hidden), $flags);
    }

    /** A run of digits is a phone number unless it is a date, a time, a date and a time, or counting (1 2 3 … 9 10). */
    private static function isPhone(string $run): bool
    {
        $ascii = strtr($run, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        // Real dates and times leave the run; what is left must still be eight digits.
        $day = fn (int $a, int $b) => ($a >= 1 && $a <= 31 && $b >= 1 && $b <= 12) || ($a >= 1 && $a <= 12 && $b >= 1 && $b <= 31);
        $year = fn (string $y) => strlen($y) === 2 || ((int) $y >= 1900 && (int) $y <= 2100);
        $rest = (string) preg_replace_callback('/(?<!\d)(\d{4})[\-\/.](\d{1,2})[\-\/.](\d{1,2})(?!\d)/',
            fn (array $m) => $year($m[1]) && $day((int) $m[3], (int) $m[2]) ? ' ' : $m[0], $ascii);
        $rest = (string) preg_replace_callback('/(?<!\d)(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{4}|\d{2})(?!\d)/',
            fn (array $m) => $year($m[3]) && $day((int) $m[1], (int) $m[2]) ? ' ' : $m[0], $rest);
        $rest = (string) preg_replace_callback('/(?<!\d)(\d{1,2}):(\d{2})(?::\d{2})?(?!\d)/',
            fn (array $m) => (int) $m[1] <= 23 && (int) $m[2] <= 59 ? ' ' : $m[0], $rest);
        if (preg_match_all('/\d/', $rest) < 8) {
            return false;
        }
        $numbers = array_map('intval', preg_split('/\D+/', trim($ascii), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if (count($numbers) >= 4) {
            $counting = true;
            for ($i = 1; $i < count($numbers); $i++) {
                $counting = $counting && abs($numbers[$i] - $numbers[$i - 1]) === 1;
            }
            if ($counting) {
                return false;
            }
        }

        return true;
    }

    /** @return array{0: string, 1: string} the match without the punctuation that ends a sentence, and that punctuation */
    private static function trimTail(string $match): array
    {
        $body = rtrim($match, ".,;:!?)]}،؛؟ \t\n");

        return [$body, substr($match, strlen($body))];
    }

    private function allowed(string $url): bool
    {
        $candidate = preg_match('/^[a-z]+:\/\//i', $url) === 1 ? $url : 'http://'.$url;
        $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));

        return $host !== '' && in_array($host, $this->allowedHosts, true);
    }
}
