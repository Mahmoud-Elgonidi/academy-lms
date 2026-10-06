<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Models\Node;

/** Swaps a node with its neighbour in the same parent (teaching order). */
final class MoveNode
{
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
}
