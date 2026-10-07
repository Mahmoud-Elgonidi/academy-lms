<?php

namespace Modules\Import\Support;

/** Text compared the way people write it: case, Arabic letter forms, diacritics, spaces and punctuation aside. */
final class Text
{
    public static function key(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;    // diacritics, tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);

        return preg_replace('/[\s_\-.:،,؛;\/\\\\()\[\]"\'`*#?!]+/u', '', $text) ?? $text;
    }

    /** Latin digits for Arabic and Persian ones. */
    public static function digits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /** @return list<string> "قرآن، تجويد / نحو" → ["قرآن", "تجويد", "نحو"] */
    public static function list(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,،;؛|\/\n]+/u', $text) ?: []), fn ($v) => $v !== ''));
    }
}
