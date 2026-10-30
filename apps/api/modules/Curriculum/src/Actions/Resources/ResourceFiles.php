<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Models\NodeResource;

/**
 * Lesson files leave the private disk after the commit that took their rows
 * off, and only when no lesson points at them any more: a copied program's
 * materials share the file of the lesson they were copied from (CopyProgram).
 */
final class ResourceFiles
{
    /** @param  list<string>  $paths */
    public static function forgetAfterCommit(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));
        if ($paths === []) {
            return;
        }
        DB::afterCommit(function () use ($paths) {
            $kept = NodeResource::query()->whereIn('file_path', $paths)->pluck('file_path')->all();
            $gone = array_values(array_diff($paths, $kept));
            if ($gone !== []) {
                Storage::disk(NodeResource::DISK)->delete($gone);
            }
        });
    }
}
