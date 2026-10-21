<?php

namespace Modules\Curriculum\Actions\Practice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Practice\ItemRules;

/**
 * Sets a lesson's practice items, in order (docs/architecture/02, section
 * 18): an item given with its id keeps it (what learners practised on it
 * stays theirs), one left out is taken off, a new one gets a new id. At most
 * MAX a lesson; each in one of ItemKind's shapes, within its lengths (ItemRules). Like
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
        $items = array_values(array_map(fn ($item) => ItemRules::clean((array) $item), $items));
        ItemRules::validator($items, self::MAX)->validate();

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
}
