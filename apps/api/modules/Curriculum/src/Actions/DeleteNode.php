<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;

/** Removes a node and what is under it, while its version is still a draft. Published lessons stay: progress points at them. */
final class DeleteNode
{
    public function handle(Node $node): void
    {
        $status = $node->version()->value('status');
        if ($status !== 'draft') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.published_delete')]);
        }

        $node->delete();
    }
}
