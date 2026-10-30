<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Models\NodeResource;

/** Takes a resource off its lesson; its file leaves the disk once that is committed, unless a copied lesson still points at it. */
final class RemoveNodeResource
{
    public function handle(NodeResource $resource): void
    {
        DB::transaction(function () use ($resource) {
            $path = $resource->file_path;
            $resource->delete();
            ResourceFiles::forgetAfterCommit($path === null ? [] : [$path]);
        });
    }
}
