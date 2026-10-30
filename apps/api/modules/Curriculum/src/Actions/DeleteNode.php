<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\Resources\ResourceFiles;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeResource;

/** Removes a node and what is under it (with its lessons' files), while its version is still a draft. Published lessons stay: progress points at them. */
final class DeleteNode
{
    public function handle(Node $node): void
    {
        $status = $node->version()->value('status');
        if ($status !== 'draft') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.published_delete')]);
        }

        DB::transaction(function () use ($node) {
            $under = [$node->id];
            for ($level = [$node->id]; $level !== [];) {
                $level = Node::query()->whereIn('parent_id', $level)->pluck('id')->all();
                array_push($under, ...$level);
            }
            $files = NodeResource::query()->whereIn('node_id', $under)->whereNotNull('file_path')->pluck('file_path')->all();
            $node->delete();
            ResourceFiles::forgetAfterCommit($files);
        });
    }
}
