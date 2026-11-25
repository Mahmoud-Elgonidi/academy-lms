<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;

/**
 * Changes a node's place in teaching order: a step up or down (handle, the
 * builder's arrows), or a place dropped on (place, the builder's drag and
 * drop). A level or unit keeps its parent and steps among its siblings; a
 * lesson may move to another unit of the same level, or straight into that
 * level, and never leaves its level. The arrows walk a lesson through its
 * level as the screen numbers it: past the first (or last) lesson of a unit
 * it goes to the end of the unit before (the start of the unit after), or
 * next to the unit when a loose lesson sits there; a loose lesson steps into
 * the unit beside it. Nothing in a retired version moves.
 */
final class MoveNode
{
    /** Moves a node one step up or down (a lesson across the units of its level) and normalizes the ordinals. */
    public function handle(Node $node, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.move')]);
        }
        $up = $direction === 'up';

        DB::transaction(function () use ($node, $up) {
            if ($node->version()->value('status') === 'retired') {
                throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.retired')]);
            }
            $node = Node::query()->lockForUpdate()->find($node->id);
            if ($node === null) {
                return;
            }

            $siblings = $this->children($node->version_id, $node->parent_id);
            $index = array_search($node->id, array_map(fn (Node $n) => $n->id, $siblings), true);
            $position = $index + ($up ? -1 : 1);

            if ($node->kind !== 'lesson') {
                if ($position >= 0 && $position < count($siblings)) {
                    $this->place($node, $node->parent_id, $position);
                }

                return;
            }

            $parent = $node->parent_id === null ? null : Node::query()->find($node->parent_id);
            if ($position >= 0 && $position < count($siblings)) {
                $next = $siblings[$position];
                if ($next->kind === 'unit') {
                    // A loose lesson steps into the unit beside it: its end going up, its start going down.
                    $this->place($node, $next->id, $up ? PHP_INT_MAX : 0);
                } else {
                    $this->place($node, $node->parent_id, $position);
                }

                return;
            }
            if ($parent?->kind !== 'unit') {
                return; // the first or last of its level: lessons never leave it
            }

            // Past the edge of its unit: to the unit beside it in the level, or next to its unit when a loose lesson is there.
            $around = $this->children($node->version_id, $parent->parent_id);
            $at = array_search($parent->id, array_map(fn (Node $n) => $n->id, $around), true);
            $beside = $around[$at + ($up ? -1 : 1)] ?? null;
            if ($beside === null) {
                return;
            }
            if ($beside->kind === 'unit') {
                $this->place($node, $beside->id, $up ? PHP_INT_MAX : 0);
            } else {
                $this->place($node, $parent->parent_id, $up ? $at : $at + 1);
            }
        });
    }

    /** @return list<Node> a parent's children in order, locked */
    private function children(string $versionId, ?string $parentId): array
    {
        return Node::query()->where('version_id', $versionId)->where('parent_id', $parentId)
            ->orderBy('ordinal')->orderBy('id')->lockForUpdate()->get()->all();
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
