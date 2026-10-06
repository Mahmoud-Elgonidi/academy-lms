<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;

/**
 * Adds a level, unit or lesson at the end of its parent, or edits one.
 * Levels sit at the top, units in levels, lessons in units (or straight in a
 * level). Moving a node to another parent is not done here.
 */
final class SaveNode
{
    private const PARENTS = ['level' => [null], 'unit' => ['level'], 'lesson' => ['unit', 'level']];

    /** @param  array{kind?: string, parent_id?: ?string, title_ar: string, title_en: string, objectives?: ?string, weight?: int|string, est_sessions?: int|string, mastery_rule?: string}  $data */
    public function handle(CurriculumVersion $version, ?Node $node, array $data): Node
    {
        if ($version->status === 'retired') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.retired')]);
        }

        return DB::transaction(function () use ($version, $node, $data) {
            if ($node === null) {
                $kind = $data['kind'] ?? 'lesson';
                $parent = isset($data['parent_id']) ? Node::query()->where('version_id', $version->id)->find($data['parent_id']) : null;
                if (! in_array($kind, Node::KINDS, true) || ! in_array($parent?->kind, self::PARENTS[$kind], true)) {
                    throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.place')]);
                }
                $node = new Node([
                    'version_id' => $version->id,
                    'parent_id' => $parent?->id,
                    'kind' => $kind,
                    'ordinal' => (int) Node::query()->where('version_id', $version->id)->where('parent_id', $parent?->id)->max('ordinal') + 1,
                ]);
            }

            $node->fill([
                'title_ar' => trim($data['title_ar']),
                'title_en' => trim($data['title_en']),
                'objectives' => ($data['objectives'] ?? null) ?: null,
                'weight' => max(1, min(100, (int) ($data['weight'] ?? $node->weight ?? 1))),
                'est_sessions' => max(1, min(50, (int) ($data['est_sessions'] ?? $node->est_sessions ?? 1))),
                'mastery_rule' => in_array($data['mastery_rule'] ?? null, Node::MASTERY_RULES, true) ? $data['mastery_rule'] : ($node->mastery_rule ?? 'mastered_once'),
            ])->save();

            return $node;
        });
    }
}
