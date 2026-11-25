<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\Resources\ResourceFiles;
use Modules\Curriculum\Contracts\Events\LessonsRemoved;
use Modules\Curriculum\Contracts\NodeUses;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeResource;

/**
 * Removes a level, unit or lesson and what is under it (with its lessons'
 * files). In a draft, always. In a published version, only while no learner
 * left anything on it or under it (NodeUses: reports, progress, homework,
 * practice); else refused with why and what to do instead (rename or move
 * it). The check runs inside the transaction after locking the nodes: a
 * report or progress row (both hold a foreign key to the node) written
 * meanwhile either committed first and is seen, or waits for this delete and
 * then finds the node gone. Courses on the version count their progress
 * again after it (LessonsRemoved). Nothing in a retired version goes.
 */
final class DeleteNode
{
    public function __construct(private readonly NodeUses $uses) {}

    public function handle(Node $node): void
    {
        $removed = DB::transaction(function () use ($node) {
            $status = CurriculumVersion::query()->whereKey($node->version_id)->lockForUpdate()->value('status');
            if ($status !== 'draft' && $status !== 'published') {
                throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.retired')]);
            }

            $under = [$node->id];
            for ($level = [$node->id]; $level !== [];) {
                $level = Node::query()->whereIn('parent_id', $level)->pluck('id')->all();
                array_push($under, ...$level);
            }

            if ($status === 'published') {
                Node::query()->whereIn('id', $under)->lockForUpdate()->get(['id']);
                $uses = $this->uses->uses($under);
                if ($uses !== []) {
                    throw ValidationException::withMessages(['node' => __('curriculum::curriculum.errors.in_use.'.$uses[0], [
                        'title' => $node->toData()->title(),
                    ])]);
                }
            }

            $files = NodeResource::query()->whereIn('node_id', $under)->whereNotNull('file_path')->pluck('file_path')->all();
            Node::query()->whereKey($node->id)->delete();
            ResourceFiles::forgetAfterCommit($files);

            return $status === 'published' ? $under : null;
        });

        if ($removed !== null) {
            DB::afterCommit(fn () => LessonsRemoved::dispatch($node->version_id, $removed));
        }
    }
}
