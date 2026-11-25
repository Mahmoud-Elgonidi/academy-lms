<?php

namespace Modules\Curriculum\Queries;

use Modules\Curriculum\Contracts\NodeUses;

/** Every module's uses of curriculum nodes (each tagged NodeUses::SOURCES), asked together. With none, nothing is used. */
final class AllNodeUses implements NodeUses
{
    public function uses(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }
        $found = [];
        foreach (app()->tagged(NodeUses::SOURCES) as $source) {
            /** @var NodeUses $source */
            array_push($found, ...$source->uses($nodeIds));
        }

        return array_values(array_intersect(NodeUses::KINDS, $found));
    }
}
