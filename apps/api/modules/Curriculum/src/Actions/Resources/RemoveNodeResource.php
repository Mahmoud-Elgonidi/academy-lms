<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Models\NodeResource;

/** Takes a resource off its lesson; its file leaves the disk once that is committed. */
final class RemoveNodeResource
{
    public function handle(NodeResource $resource): void
    {
        DB::transaction(function () use ($resource) {
            $path = $resource->file_path;
            $resource->delete();
            if ($path !== null) {
                DB::afterCommit(fn () => Storage::disk(NodeResource::DISK)->delete($path));
            }
        });
    }
}
