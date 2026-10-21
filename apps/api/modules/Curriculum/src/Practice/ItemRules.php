<?php

namespace Modules\Curriculum\Practice;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Modules\SharedKernel\Games\ItemKind;

/**
 * What a practice item may be (docs/architecture/02, section 18): one of
 * ItemKind's shapes within its lengths, a choice's answer among its own
 * options, no option twice. SaveNodeItems holds a lesson's items to it, and
 * a suggested item that misses it is never offered.
 */
final class ItemRules
{
    /**
     * Trims every text and drops empty entries, so a blank option or part is not an item.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function clean(array $item): array
    {
        $text = fn ($v) => is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v) ?? '') : $v;
        $list = fn ($v) => is_array($v) ? array_values(array_filter(array_map($text, $v), fn ($s) => is_string($s) && $s !== '')) : $v;
        $clean = [
            'id' => ($item['id'] ?? null) ?: null,
            'kind' => $item['kind'] ?? null,
            'prompt' => ($p = $text($item['prompt'] ?? null)) === '' ? null : $p,
        ];
        foreach (['options' => $list, 'parts' => $list, 'left' => $text, 'right' => $text] as $key => $fn) {
            if (array_key_exists($key, $item)) {
                $clean[$key] = $fn($item[$key]);
            }
        }
        if (array_key_exists('answer', $item)) {
            $clean['answer'] = is_numeric($item['answer']) ? (int) $item['answer'] : $item['answer'];
        }

        return $clean;
    }

    /** @param  list<array<string, mixed>>  $items  cleaned */
    public static function validator(array $items, int $max): ValidatorContract
    {
        $p = 'curriculum::curriculum.practice.fields.';

        return Validator::make(['items' => $items], [
            'items' => ['array', 'max:'.$max],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.kind' => ['required', 'in:'.implode(',', ItemKind::values())],
            'items.*.prompt' => ['nullable', 'string', 'max:'.ItemKind::PROMPT_MAX, 'required_if:items.*.kind,choice'],
            'items.*.options' => ['exclude_unless:items.*.kind,choice', 'array', 'min:2', 'max:4'],
            'items.*.options.*' => ['required', 'string', 'max:'.ItemKind::OPTION_MAX],
            'items.*.answer' => ['exclude_unless:items.*.kind,choice', 'required', 'integer', 'min:0'],
            'items.*.parts' => ['exclude_unless:items.*.kind,order', 'array', 'min:2', 'max:8'],
            'items.*.parts.*' => ['required', 'string', 'max:'.ItemKind::PART_MAX],
            'items.*.left' => ['exclude_unless:items.*.kind,pair', 'required', 'string', 'max:'.ItemKind::SIDE_MAX],
            'items.*.right' => ['exclude_unless:items.*.kind,pair', 'required', 'string', 'max:'.ItemKind::SIDE_MAX],
        ], [], [
            'items' => __($p.'items'),
            'items.*.kind' => __($p.'kind'),
            'items.*.prompt' => __($p.'prompt'),
            'items.*.options' => __($p.'options'),
            'items.*.options.*' => __($p.'option'),
            'items.*.answer' => __($p.'answer'),
            'items.*.parts' => __($p.'parts'),
            'items.*.parts.*' => __($p.'part'),
            'items.*.left' => __($p.'left'),
            'items.*.right' => __($p.'right'),
        ])->after(function ($v) use ($items) {
            foreach ($items as $i => $item) {
                if (($item['kind'] ?? null) === 'choice' && ! array_key_exists((int) ($item['answer'] ?? -1), (array) ($item['options'] ?? []))) {
                    $v->errors()->add("items.$i.answer", __('curriculum::curriculum.practice.errors.answer'));
                }
                // An item's options differ from each other (other items may share them).
                $options = array_map(fn ($o) => is_string($o) ? mb_strtolower($o) : $o, (array) ($item['options'] ?? []));
                if (($item['kind'] ?? null) === 'choice' && count($options) !== count(array_unique($options))) {
                    $v->errors()->add("items.$i.options", __('curriculum::curriculum.practice.errors.same_option'));
                }
            }
        });
    }

    /** @param  array<string, mixed>  $item  cleaned */
    public static function passes(array $item): bool
    {
        return ! self::validator([$item], 1)->fails();
    }
}
