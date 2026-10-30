<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;

/**
 * Changes a node's place in teaching order: a step up or down among its
 * siblings (handle, the builder's arrows), or a place dropped on (place, the
 * builder's drag and drop). A level or unit keeps its parent; a lesson may move
 * to another unit of the same level, or straight into that level, and never
 * leaves its level. Nothing in a retired version moves.
 */
final class MoveNode
{
    /** Swaps a node with its neighbour in the same parent. */
    public function handle(Node $node, string $direction): void
    {
        DB::transaction(function () use ($node, $direction) {
            $neighbour = Node::query()->where('version_id', $node->version_id)->where('parent_id', $node->parent_id)
                ->where('ordinal', $direction === 'up' ? '<' : '>', $node->ordinal)
                ->orderBy('ordinal', $direction === 'up' ? 'desc' : 'asc')
                ->lockForUpdate()->first();
            if ($neighbour === null) {
                return;
            }

            [$node->ordinal, $neighbour->ordinal] = [$neighbour->ordinal, $node->ordinal];
            $node->save();
            $neighbour->save();
        });
    }

    /**
     * Puts a node at $position (from 0) among the children of $parentId (null: the top), numbering
     * that parent's children again in their new order.
     */
    public function place(Node $node, ?string $parentId, int $position): void
    {
        $refuse = fn () => throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.move')]);
        if ($node->version()->value('status') === 'retired') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.retired')]);
        }

        DB::transaction(function () use ($node, $parentId, $position, $refuse) {
            $node = Node::query()->lockForUpdate()->findOrFail($node->id);
            $parent = $parentId === null ? null : Node::query()->where('version_id', $node->version_id)->find($parentId);
            if ($parentId !== null && $parent === null) {
                $refuse();
            }
            if ($parent?->id !== $node->parent_id) {
                // Only a lesson changes parent, to a place a lesson may sit, within its own level.
                if ($node->kind !== 'lesson' || ! in_array($parent?->kind, Node::PARENTS['lesson'], true)
                    || $this->levelOf($node->parent_id) !== $this->levelOf($parent?->id)) {
                    $refuse();
                }
            }

            $siblings = Node::query()->where('version_id', $node->version_id)->where('parent_id', $parent?->id)
                ->whereKeyNot($node->id)->orderBy('ordinal')->orderBy('id')->lockForUpdate()->get()->all();
            array_splice($siblings, max(0, min($position, count($siblings))), 0, [$node]);

            $node->parent_id = $parent?->id;
            foreach ($siblings as $i => $sibling) {
                if ($sibling->ordinal !== $i + 1 || $sibling->is($node)) {
                    $sibling->ordinal = $i + 1;
                    $sibling->save();
                }
            }
        });
    }

    /** The level a node sits in (itself when it is one). */
    private function levelOf(?string $id): ?string
    {
        for ($node = $id === null ? null : Node::query()->find($id); $node !== null; $node = $node->parent_id === null ? null : Node::query()->find($node->parent_id)) {
            if ($node->kind === 'level') {
                return $node->id;
            }
        }

        return null;
    }
}
