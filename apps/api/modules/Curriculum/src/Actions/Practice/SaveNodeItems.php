<?php

namespace Modules\Curriculum\Actions\Practice;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\SharedKernel\Games\ItemKind;

/**
 * Sets a lesson's practice items, in order (docs/architecture/02, section
 * 18): an item given with its id keeps it (what learners practised on it
 * stays theirs), one left out is taken off, a new one gets a new id. At most
 * MAX a lesson; each in one of ItemKind's shapes, within its lengths. Like
 * the lesson's materials, practice can change on a published curriculum.
 */
final class SaveNodeItems
{
    public const MAX = 40;

    /**
     * @param  list<array<string, mixed>>  $items  {id?, kind, prompt?, options?, answer?, parts?, left?, right?}
     * @return list<NodeItem>
     */
    public function handle(Node $lesson, array $items, ?string $actorId): array
    {
        if ($lesson->kind !== 'lesson') {
            throw ValidationException::withMessages(['items' => __('curriculum::curriculum.practice.errors.not_lesson')]);
        }
        $items = array_values(array_map(fn ($item) => self::clean((array) $item), $items));
        $kinds = ItemKind::values();
        $p = 'curriculum::curriculum.practice.fields.';
        Validator::make(['items' => $items], [
            'items' => ['array', 'max:'.self::MAX],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.kind' => ['required', 'in:'.implode(',', $kinds)],
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
                if ($item['kind'] === 'choice' && ! array_key_exists((int) ($item['answer'] ?? -1), $item['options'] ?? [])) {
                    $v->errors()->add("items.$i.answer", __('curriculum::curriculum.practice.errors.answer'));
                }
                // An item's options differ from each other (other items may share them).
                $options = array_map(fn ($o) => is_string($o) ? mb_strtolower($o) : $o, (array) ($item['options'] ?? []));
                if ($item['kind'] === 'choice' && count($options) !== count(array_unique($options))) {
                    $v->errors()->add("items.$i.options", __('curriculum::curriculum.practice.errors.same_option'));
                }
            }
        })->validate();

        return DB::transaction(function () use ($lesson, $items, $actorId) {
            $kept = array_values(array_filter(array_column($items, 'id')));
            $own = NodeItem::query()->where('node_id', $lesson->id)->lockForUpdate()->pluck('id')->all();
            if (array_diff($kept, $own) !== []) {
                throw ValidationException::withMessages(['items' => __('curriculum::curriculum.practice.errors.foreign')]);
            }
            NodeItem::query()->where('node_id', $lesson->id)->whereNotIn('id', $kept)->delete();

            $saved = [];
            foreach ($items as $ordinal => $item) {
                $row = isset($item['id']) ? NodeItem::query()->where('node_id', $lesson->id)->findOrFail($item['id']) : new NodeItem(['node_id' => $lesson->id, 'created_by' => $actorId]);
                $row->fill([
                    'ordinal' => $ordinal + 1,
                    'kind' => $item['kind'],
                    'prompt' => $item['kind'] === 'pair' ? null : ($item['prompt'] ?? null),
                    'data' => match ($item['kind']) {
                        'choice' => ['options' => $item['options'], 'answer' => (int) $item['answer']],
                        'order' => ['parts' => $item['parts']],
                        'pair' => ['left' => $item['left'], 'right' => $item['right']],
                    },
                ])->save();
                $saved[] = $row;
            }

            return $saved;
        });
    }

    /**
     * Trims every text and drops empty entries, so a blank option or part is not an item.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function clean(array $item): array
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
}
