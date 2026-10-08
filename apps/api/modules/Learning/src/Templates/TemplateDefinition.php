<?php

namespace Modules\Learning\Templates;

use Illuminate\Validation\ValidationException;
use Modules\Learning\Models\ReportTemplate;

/**
 * Reads the questions the editor sends into template fields and checks
 * them: at most twelve, each with words in Arabic or English (the other
 * language takes the same words when left empty), a known kind of answer,
 * two to eight choices for a choice, a sensible range for a number.
 *
 * Keys stay with their question: a question kept from the version before
 * (the same key, the same kind of answer) keeps its key, so its answers
 * line up across versions; anything new gets a key never used by this
 * subject before (f1, f2, …), and so does a question whose kind changed.
 */
final class TemplateDefinition
{
    public const MAX_FIELDS = 12;

    public const MAX_OPTIONS = 8;

    /**
     * @param  list<array<string, mixed>>  $input
     * @param  list<ReportTemplate>  $versions  every earlier version of the subject's template, latest first
     * @return list<TemplateField>
     */
    public static function read(array $input, array $versions): array
    {
        $input = array_values(array_filter($input, fn ($f) => is_array($f)));
        if (count($input) > self::MAX_FIELDS) {
            self::fail('fields', __('learning::templates.errors.too_many', ['max' => self::MAX_FIELDS]));
        }

        $previous = collect($versions[0]->fields ?? [])->keyBy('key');
        $used = collect($versions)->flatMap(fn (ReportTemplate $t) => array_column($t->fields, 'key'))
            ->map(fn (string $k) => (int) ltrim($k, 'f'))->max() ?? 0;
        $taken = [];

        return array_map(function (array $f, int $i) use ($previous, &$used, &$taken) {
            $at = 'fields.'.$i;
            [$ar, $en] = self::words($f['label_ar'] ?? null, $f['label_en'] ?? null, 120, $at.'.label', __('learning::templates.errors.label', ['n' => $i + 1]));
            $type = (string) ($f['type'] ?? '');
            if (! in_array($type, TemplateField::TYPES, true)) {
                self::fail($at.'.type', __('learning::templates.errors.type', ['n' => $i + 1]));
            }

            $key = is_string($f['key'] ?? null) ? $f['key'] : null;
            $before = $key !== null && ! isset($taken[$key]) ? $previous->get($key) : null;
            if ($before === null || $before['type'] !== $type) {
                $key = 'f'.(++$used);
            }
            $taken[$key] = true;

            $options = [];
            $range = ['min' => null, 'max' => null];
            $unitAr = $unitEn = $unitArFew = null;
            if ($type === 'choice') {
                $options = self::options($f['options'] ?? [], $before['options'] ?? [], $at, $i);
            }
            if ($type === 'number') {
                foreach ($range as $end => $_) {
                    $value = $f[$end] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (! is_numeric($value) || (int) $value != $value || $value < 0 || $value > 100000) {
                        self::fail($at.'.'.$end, __('learning::templates.errors.range', ['n' => $i + 1]));
                    }
                    $range[$end] = (int) $value;
                }
                if ($range['min'] !== null && $range['max'] !== null && $range['min'] > $range['max']) {
                    self::fail($at.'.max', __('learning::templates.errors.range', ['n' => $i + 1]));
                }
                [$unitAr, $unitEn] = self::words($f['unit_ar'] ?? null, $f['unit_en'] ?? null, 20, $at.'.unit', __('learning::templates.errors.unit', ['n' => $i + 1]), optional: true);
                $unitArFew = trim((string) ($f['unit_ar_few'] ?? '')) ?: null;
                if ($unitArFew !== null && mb_strlen($unitArFew) > 20) {
                    self::fail($at.'.unit', __('learning::templates.errors.unit', ['n' => $i + 1]));
                }
            }

            return new TemplateField(
                key: $key,
                labelAr: $ar,
                labelEn: $en,
                type: $type,
                required: filter_var($f['required'] ?? false, FILTER_VALIDATE_BOOL),
                family: filter_var($f['family'] ?? true, FILTER_VALIDATE_BOOL),
                options: $options,
                min: $range['min'],
                max: $range['max'],
                unitAr: $unitAr,
                unitEn: $unitEn,
                unitArFew: $unitAr === null ? null : $unitArFew,
            );
        }, $input, array_keys($input));
    }

    /**
     * Two to eight choices; a choice kept from before keeps its key (o1, o2, …).
     *
     * @param  list<array{key: string, ar: string, en: string}>  $before
     * @return list<array{key: string, ar: string, en: string}>
     */
    private static function options(mixed $input, array $before, string $at, int $i): array
    {
        $input = array_values(array_filter(is_array($input) ? $input : [], fn ($o) => is_array($o) && trim((string) ($o['ar'] ?? '').($o['en'] ?? '')) !== ''));
        if (count($input) < 2 || count($input) > self::MAX_OPTIONS) {
            self::fail($at.'.options', __('learning::templates.errors.options', ['n' => $i + 1, 'max' => self::MAX_OPTIONS]));
        }
        $known = array_column($before, null, 'key');
        $used = collect($before)->map(fn (array $o) => (int) ltrim($o['key'], 'o'))->max() ?? 0;
        $taken = [];

        return array_map(function (array $o) use ($known, &$used, &$taken, $at, $i) {
            [$ar, $en] = self::words($o['ar'] ?? null, $o['en'] ?? null, 60, $at.'.options', __('learning::templates.errors.options', ['n' => $i + 1, 'max' => self::MAX_OPTIONS]));
            $key = is_string($o['key'] ?? null) && isset($known[$o['key']]) && ! isset($taken[$o['key']]) ? $o['key'] : 'o'.(++$used);
            $taken[$key] = true;

            return ['key' => $key, 'ar' => $ar, 'en' => $en];
        }, $input);
    }

    /** @return array{0: ?string, 1: ?string} the words in both languages, one standing in for the other */
    private static function words(mixed $ar, mixed $en, int $max, string $at, string $message, bool $optional = false): array
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);
        if ($ar === '' && $en === '') {
            if ($optional) {
                return [null, null];
            }
            self::fail($at, $message);
        }
        if (mb_strlen($ar) > $max || mb_strlen($en) > $max) {
            self::fail($at, $message);
        }

        return [$ar !== '' ? $ar : $en, $en !== '' ? $en : $ar];
    }

    private static function fail(string $at, string $message): never
    {
        throw ValidationException::withMessages([$at => $message]);
    }
}
