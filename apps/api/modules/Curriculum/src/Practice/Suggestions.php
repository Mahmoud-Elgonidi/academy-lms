<?php

namespace Modules\Curriculum\Practice;

use Modules\Curriculum\Contracts\DTOs\Passage;
use Modules\Curriculum\Texts\QuranText;

/**
 * Turns the model's answer into items a teacher may save: each {{a-b}}
 * becomes the passage's own words as kept, an order item's word range is
 * cut into its parts here, and an item is dropped when it quotes outside
 * the passage, writes three words of the Quran itself instead of quoting
 * them, or misses ItemRules. Nothing is saved: the teacher sees them first.
 */
final class Suggestions
{
    private const QUOTE = '/\{\{\s*(\d+)\s*(?:[-–]\s*(\d+))?\s*\}\}/u';

    /** At most this many parts in an order item (ItemRules), each of a few words. */
    private const PARTS_MAX = 8;

    /**
     * @param  array<string, mixed>  $data  the model's answer
     * @param  list<string>  $existing  summaries of the lesson's items, not to be offered again
     * @return array{items: list<array<string, mixed>>, dropped: int}
     */
    public static function read(array $data, ?Passage $passage, QuranText $quran, int $count, array $existing = []): array
    {
        $items = [];
        $dropped = 0;
        $seen = array_flip(array_map('mb_strtolower', $existing));
        foreach (array_slice(array_values((array) ($data['items'] ?? [])), 0, $count) as $raw) {
            $item = is_array($raw) ? self::item($raw, $passage, $quran) : null;
            $key = $item ? mb_strtolower(self::summary($item)) : null;
            if ($item === null || isset($seen[$key])) {
                $dropped++;

                continue;
            }
            $seen[$key] = true;
            $items[] = $item;
        }

        return ['items' => $items, 'dropped' => $dropped];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return ?array<string, mixed>
     */
    private static function item(array $raw, ?Passage $passage, QuranText $quran): ?array
    {
        $kind = $raw['kind'] ?? null;
        $texts = match ($kind) {
            'choice' => ['prompt' => $raw['prompt'] ?? '', 'options' => (array) ($raw['options'] ?? [])],
            'order' => ['prompt' => $raw['prompt'] ?? ''],
            'pair' => ['left' => $raw['left'] ?? '', 'right' => $raw['right'] ?? ''],
            default => null,
        };
        if ($texts === null) {
            return null;
        }

        // What the model wrote itself, references aside: never the Quran's words.
        $own = implode(' ', array_map(fn ($t) => preg_replace(self::QUOTE, ' ', (string) $t) ?? '', [$texts['prompt'] ?? '', ...($texts['options'] ?? []), $texts['left'] ?? '', $texts['right'] ?? '']));
        if ($passage?->source->isQuran() && $quran->quotes($own)) {
            return null;
        }

        $expand = function ($text) use ($passage): ?string {
            if (! is_string($text)) {
                return null;
            }
            $ok = true;
            $out = preg_replace_callback(self::QUOTE, function (array $m) use ($passage, &$ok) {
                $quote = $passage?->quote((int) $m[1], (int) (($m[2] ?? '') !== '' ? $m[2] : $m[1])) ?? '';
                $ok = $ok && $quote !== '';

                return $quote;
            }, $text);

            return $ok ? $out : null;
        };

        $item = ['kind' => $kind];
        foreach (['prompt', 'left', 'right'] as $field) {
            if (array_key_exists($field, $texts)) {
                $value = $expand($texts[$field]);
                if ($value === null) {
                    return null;
                }
                $item[$field] = $value;
            }
        }
        if ($kind === 'choice') {
            $options = array_map($expand, $texts['options']);
            if (in_array(null, $options, true)) {
                return null;
            }
            $item['options'] = $options;
            $item['answer'] = is_numeric($raw['answer'] ?? null) ? (int) $raw['answer'] : -1;
        }
        if ($kind === 'order') {
            $parts = self::parts($passage, (int) ($raw['parts_from'] ?? 0), (int) ($raw['parts_to'] ?? 0));
            if ($parts === null) {
                return null;
            }
            $item['parts'] = $parts;
        }

        $item = ItemRules::clean($item);
        unset($item['id']);

        return ItemRules::passes($item) ? $item : null;
    }

    /**
     * The words $from to $to as parts: one word each, or as many even runs as fit.
     *
     * @return ?list<string>
     */
    private static function parts(?Passage $passage, int $from, int $to): ?array
    {
        if ($passage === null || $passage->quote($from, $to) === '' || $to <= $from) {
            return null;
        }
        $words = array_slice($passage->words, $from - 1, $to - $from + 1);
        $groups = min(self::PARTS_MAX, count($words));
        $size = (int) ceil(count($words) / $groups);

        return array_map(fn (array $run) => implode(' ', $run), array_chunk($words, $size));
    }

    /** @param  array<string, mixed>  $item */
    private static function summary(array $item): string
    {
        return match ($item['kind']) {
            'choice' => trim((string) $item['prompt']).' → '.($item['options'][$item['answer']] ?? ''),
            'order' => trim(($item['prompt'] ? $item['prompt'].': ' : '').implode(' · ', $item['parts'])),
            'pair' => $item['left'].' ↔ '.$item['right'],
        };
    }
}
