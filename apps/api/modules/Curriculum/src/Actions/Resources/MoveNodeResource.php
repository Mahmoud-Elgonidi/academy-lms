<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Models\NodeResource;

/** Moves a resource one place up or down among its lesson's. */
final class MoveNodeResource
{
    public function handle(NodeResource $resource, string $direction): void
    {
        DB::transaction(function () use ($resource, $direction) {
            $up = $direction === 'up';
            $neighbour = NodeResource::query()->where('node_id', $resource->node_id)
                ->where('ordinal', $up ? '<' : '>', $resource->ordinal)
                ->orderBy('ordinal', $up ? 'desc' : 'asc')->first();
            if ($neighbour === null) {
                return;
            }
            [$a, $b] = [$resource->ordinal, $neighbour->ordinal];
            $resource->update(['ordinal' => $b]);
            $neighbour->update(['ordinal' => $a]);
        });
    }
}
