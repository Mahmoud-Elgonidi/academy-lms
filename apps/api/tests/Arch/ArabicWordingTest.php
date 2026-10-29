<?php

/*
 * Arabic is written in Modern Standard Arabic (docs/style/arabic.md): the
 * kids' look in simple words, everything else plainly. This reads every Arabic
 * wording file and fails on a colloquial word or one the glossary says
 * another way. The portal (src/lib/messages.test.ts) and the app
 * (tool/strings.py) hold the same list.
 */

/** Colloquial words, each standing on its own. */
const ARABIC_COLLOQUIAL = [
    'ده', 'دي', 'دول', 'مش', 'مفيش', 'عشان', 'علشان', 'إزاي', 'ازاي', 'إيه', 'ايه', 'فين', 'ليه', 'دلوقتي', 'كده',
    'بتاع', 'بتاعة', 'بتاعك', 'اللي', 'عايز', 'عاوز', 'يلا', 'لسه', 'بقى', 'أوي', 'تاني', 'كمان', 'بس', 'خلّي', 'خلي',
    'قايمة', 'القايمة', 'دقايق', 'إمتى', 'امتى',
];

/** The glossary: a word we do not use => the one we do. */
const ARABIC_GLOSSARY = [
    '(?:ال)?مدرّس(?:ين|ون)?' => 'معلّم',
    '(?:ال)?مدرس(?:ين|ون)?' => 'معلّم',
    'الفلوس' => 'المدفوعات',
    'الكود' => 'الرمز',
    'الدعم' => 'المساعدة',
    'الستيكرز' => 'الملصقات',
];

/** @return list<string> every Arabic string in a lang file, with its key */
function arabicStrings(array $node, string $path = ''): array
{
    $out = [];
    foreach ($node as $key => $value) {
        $here = $path === '' ? (string) $key : $path.'.'.$key;
        if (is_array($value)) {
            array_push($out, ...arabicStrings($value, $here));
        } elseif (is_string($value)) {
            $out[] = [$here, $value];
        }
    }

    return $out;
}

it('writes Arabic in Modern Standard Arabic', function () {
    $root = dirname(__DIR__, 2);
    $files = array_merge(glob($root.'/modules/*/lang/ar/*.php') ?: [], glob($root.'/lang/ar/*.php') ?: []);
    $letter = '\x{0621}-\x{064A}\x{0671}';
    $words = [];
    foreach (ARABIC_COLLOQUIAL as $word) {
        $words['(?<!['.$letter.'])'.preg_quote($word, '/').'(?!['.$letter.'])'] = 'a colloquial word';
    }
    foreach (ARABIC_GLOSSARY as $word => $right) {
        $words['(?<!['.$letter.'])'.$word.'(?!['.$letter.'])'] = '"'.$right.'"';
    }

    $wrong = [];
    foreach ($files as $file) {
        $name = substr($file, strlen($root) + 1);
        foreach (arabicStrings(require $file) as [$key, $text]) {
            foreach ($words as $pattern => $why) {
                if (preg_match('/'.$pattern.'/u', $text, $match) === 1) {
                    $wrong[] = "{$name} {$key}: \"{$match[0]}\" ({$why})";
                }
            }
        }
    }

    expect($wrong)->toBe([], 'See docs/style/arabic.md.');
});
