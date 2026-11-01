<?php

namespace Modules\Quran\Domain\Grading;

/**
 * The kinds of mistake a teacher marks, each with its weight in a grade
 * (0–5): wrong (1), hesitation (0.5) and tajweed (0.25) always, their
 * weights the academy's to change, and up to nine of the academy's own
 * (a key of lowercase letters, digits and _, and a name in each language).
 */
final class MistakeTypes
{
    public const DEFAULTS = [
        ['key' => 'wrong', 'weight' => 1.0],
        ['key' => 'hesitation', 'weight' => 0.5],
        ['key' => 'tajweed', 'weight' => 0.25],
    ];

    public const BUILT_IN = ['wrong', 'hesitation', 'tajweed'];

    public const MAX = 12;

    public const WEIGHT_MAX = 5.0;

    public const NAME_MAX = 40;

    /**
     * @param  mixed  $types  list<array{key: string, weight: float|int, name_ar?: string, name_en?: string}>
     * @return list<string> reasons (quran::settings.errors.*), empty when they are fine
     */
    public static function errors(mixed $types): array
    {
        if (! is_array($types) || ! array_is_list($types) || count($types) > self::MAX) {
            return ['types'];
        }
        $errors = [];
        $seen = [];
        foreach ($types as $type) {
            $key = is_array($type) ? ($type['key'] ?? null) : null;
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,29}$/', $key) !== 1 || isset($seen[$key])) {
                $errors[] = 'type_key';

                continue;
            }
            $seen[$key] = true;
            $weight = $type['weight'] ?? null;
            if (! is_numeric($weight) || (float) $weight < 0 || (float) $weight > self::WEIGHT_MAX) {
                $errors[] = 'type_weight';
            }
            if (! in_array($key, self::BUILT_IN, true)) {
                foreach (['name_ar', 'name_en'] as $name) {
                    $value = trim((string) ($type[$name] ?? ''));
                    if ($value === '' || mb_strlen($value) > self::NAME_MAX) {
                        $errors[] = 'type_name';
                    }
                }
            }
        }
        if (array_diff(self::BUILT_IN, array_keys($seen)) !== []) {
            $errors[] = 'type_built_in';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  list<array<string, mixed>>  $types  errors() is empty for them
     * @return list<array{key: string, weight: float, name_ar?: string, name_en?: string}>
     */
    public static function normalize(array $types): array
    {
        return array_map(fn (array $t) => in_array($t['key'], self::BUILT_IN, true)
            ? ['key' => $t['key'], 'weight' => (float) $t['weight']]
            : ['key' => $t['key'], 'weight' => (float) $t['weight'], 'name_ar' => trim((string) $t['name_ar']), 'name_en' => trim((string) $t['name_en'])], $types);
    }

    /**
     * @param  list<array{key: string, weight: float}>  $types
     * @return array<string, float> key => weight
     */
    public static function weights(array $types): array
    {
        return array_column($types, 'weight', 'key');
    }
}
