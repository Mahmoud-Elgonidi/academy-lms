<?php

/*
 * An amount in Arabic ends in its currency's abbreviation, and that ends in a
 * full stop of its own ("235 ر.س."), so an Arabic sentence never ends right
 * after an amount: it would read "235 ر.س..". The amount goes in brackets or
 * mid-sentence instead. These placeholders carry money wherever they are used.
 */

const MONEY_PLACEHOLDERS = ['amount', 'total', 'price', 'saving', 'outstanding', 'payable', 'net', 'money', 'deductions', 'bonuses', 'lesson_pay', 'tax'];

/** @return list<array{0: string, 1: string}> every string in a lang file, with its key */
function moneySentenceStrings(array $node, string $path = ''): array
{
    $out = [];
    foreach ($node as $key => $value) {
        $here = $path === '' ? (string) $key : $path.'.'.$key;
        if (is_array($value)) {
            array_push($out, ...moneySentenceStrings($value, $here));
        } elseif (is_string($value)) {
            $out[] = [$here, $value];
        }
    }

    return $out;
}

it('never ends an Arabic sentence right after an amount', function () {
    $root = dirname(__DIR__, 2);
    $pattern = '/:(?:'.implode('|', MONEY_PLACEHOLDERS).')\.(?![A-Za-z0-9_])/u';
    $wrong = [];
    foreach (array_merge(glob($root.'/modules/*/lang/ar/*.php') ?: [], glob($root.'/lang/ar/*.php') ?: []) as $file) {
        foreach (moneySentenceStrings(require $file) as [$key, $text]) {
            if (preg_match($pattern, $text) === 1) {
                $wrong[] = substr($file, strlen($root) + 1).' '.$key;
            }
        }
    }

    expect($wrong)->toBe([]);
});
