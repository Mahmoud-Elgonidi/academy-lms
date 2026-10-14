<?php

namespace Modules\Announcements\Domain;

/**
 * What an announcement may be (docs/architecture/02, section 20): a short
 * title and plain words, with their line breaks, no longer than a phone
 * screen or two; the inbox always, push and email when the academy wants
 * them, never WhatsApp (its approved template takes no free words); and a
 * few a day, so families keep reading them.
 */
final class AnnouncementRules
{
    public const TITLE_MAX = 120;

    public const BODY_MAX = 1000;

    public const PER_DAY = 5;

    /** Outside channels the academy may add to the inbox. */
    public const CHANNELS = ['push', 'email'];

    /** A title as it will be read: one line, spaces collapsed, no control characters. */
    public static function title(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', self::printable(str_replace(["\r", "\n", "\t"], ' ', $text))));
    }

    /** The words as they will be read: line breaks kept (one empty line at most), no trailing spaces, no control characters. */
    public static function body(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $text = (string) preg_replace('/[ \x{00A0}]+\n/u', "\n", self::printable($text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * What is wrong, by field: required, too_long or channel. Give it the
     * cleaned title and body.
     *
     * @param  list<string>  $channels
     * @return array<string, string>
     */
    public static function problems(string $title, string $body, array $channels): array
    {
        $problems = [];
        foreach (['title' => [$title, self::TITLE_MAX], 'body' => [$body, self::BODY_MAX]] as $field => [$text, $max]) {
            if ($text === '') {
                $problems[$field] = 'required';
            } elseif (mb_strlen($text) > $max) {
                $problems[$field] = 'too_long';
            }
        }
        if (array_diff($channels, self::CHANNELS) !== []) {
            $problems['channels'] = 'channel';
        }

        return $problems;
    }

    /**
     * The outside channels chosen, in a fixed order, each once.
     *
     * @param  list<string>  $channels
     * @return list<string>
     */
    public static function channels(array $channels): array
    {
        return array_values(array_intersect(self::CHANNELS, $channels));
    }

    /** How many more the academy may send today. */
    public static function left(int $sentToday): int
    {
        return max(0, self::PER_DAY - $sentToday);
    }

    private static function printable(string $text): string
    {
        // C0 and C1 control characters, the line break aside (marks of direction and joiners stay: Arabic needs them).
        return (string) preg_replace('/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u', '', $text);
    }
}
