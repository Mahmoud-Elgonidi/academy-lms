<?php

namespace Modules\Curriculum\Actions\Outline;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Copies a program of the academy (its published version, else its draft)
 * into a draft: as a new program (handle), or into a program whose draft is
 * still empty (into). Levels, units and lessons go through SaveNode in their
 * order, with objectives, weights, sessions, mastery rules and texts; practice
 * through SaveNodeItems; materials as rows that point at the same file on the
 * private disk, never a second copy of its bytes: a file leaves the disk only
 * when no lesson points at it any more (Resources\ResourceFiles), and the
 * source's rows are held (for share) while they are read, so one taken off at
 * the same moment waits for the copy. Another academy's program is not found
 * (RLS). Audited with the counts.
 */
final class CopyProgram
{
    public function __construct(
        private readonly SaveProgram $programs,
        private readonly SaveNode $save,
        private readonly SaveNodeItems $items,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  array{name_ar: string, name_en: string}  $names */
    public function handle(string $sourceId, array $names, ?string $actorId): Program
    {
        return DB::transaction(function () use ($sourceId, $names, $actorId) {
            $source = $this->source($sourceId);
            $program = $this->programs->handle(null, [
                'subject_id' => $source->subject_id,
                'name_ar' => $names['name_ar'],
                'name_en' => $names['name_en'],
                'description_ar' => $source->description_ar,
                'description_en' => $source->description_en,
            ]);
            $this->copy($source, $program->workingVersion(), $actorId);

            return $program;
        });
    }

    /** Fills $target's empty draft with $sourceId's content. */
    public function into(string $sourceId, Program $target, ?string $actorId): CurriculumVersion
    {
        return DB::transaction(function () use ($sourceId, $target, $actorId) {
            $source = $this->source($sourceId);
            $version = $target->workingVersion();
            CurriculumVersion::query()->whereKey($version->id)->lockForUpdate()->first();
            if ($source->id === $target->id || $version->status !== 'draft' || Node::query()->where('version_id', $version->id)->exists()) {
                throw ValidationException::withMessages(['program' => __('curriculum::outline.copy.errors.not_empty')]);
            }
            $this->copy($source, $version, $actorId);

            return $version;
        });
    }

    private function source(string $id): Program
    {
        return Program::query()->whereKey($id)->first()
            ?? throw ValidationException::withMessages(['program' => __('curriculum::outline.copy.errors.missing')]);
    }

    private function copy(Program $source, CurriculumVersion $into, ?string $actorId): void
    {
        $from = $source->versions()->where('status', 'published')->first() ?? $source->versions()->where('status', 'draft')->first();
        if ($from === null || ! Node::query()->where('version_id', $from->id)->exists()) {
            throw ValidationException::withMessages(['program' => __('curriculum::outline.copy.errors.empty')]);
        }

        $nodes = Node::query()->where('version_id', $from->id)->orderBy('ordinal')->orderBy('id')->get()->groupBy(fn (Node $n) => (string) $n->parent_id);
        $ids = Node::query()->where('version_id', $from->id)->pluck('id');
        $items = NodeItem::query()->whereIn('node_id', $ids)->orderBy('ordinal')->get()->groupBy('node_id');
        $resources = NodeResource::query()->whereIn('node_id', $ids)->orderBy('ordinal')->sharedLock()->get()->groupBy('node_id');

        $counts = ['levels' => 0, 'units' => 0, 'lessons' => 0, 'items' => 0, 'materials' => 0];
        $walk = function (string $parent, ?Node $copyParent) use (&$walk, $nodes, $items, $resources, $into, $actorId, &$counts) {
            foreach ($nodes[$parent] ?? [] as $node) {
                $copy = $this->save->handle($into, null, [
                    'kind' => $node->kind,
                    'parent_id' => $copyParent?->id,
                    'title_ar' => $node->title_ar,
                    'title_en' => $node->title_en,
                    'objectives' => $node->objectives,
                    'weight' => $node->weight,
                    'est_sessions' => $node->est_sessions,
                    'mastery_rule' => $node->mastery_rule,
                ] + ($node->text_source !== null ? ['text_source' => $node->text_source] : []));
                $counts[$node->kind.'s']++;

                $practice = ($items[$node->id] ?? collect())->map(fn (NodeItem $i) => ['kind' => $i->kind, 'prompt' => $i->prompt] + $i->data)->values()->all();
                if ($practice !== []) {
                    $this->items->handle($copy, $practice, $actorId);
                    $counts['items'] += count($practice);
                }
                foreach ($resources[$node->id] ?? [] as $resource) {
                    NodeResource::query()->create(['node_id' => $copy->id, 'created_by' => $actorId] + $resource->only([
                        'ordinal', 'kind', 'title_ar', 'title_en', 'audience', 'file_path', 'file_name', 'mime', 'size', 'url',
                    ]));
                    $counts['materials']++;
                }
                $walk($node->id, $copy);
            }
        };
        $walk('', null);

        $this->audit->record('curriculum.program_copied', 'curriculum_version', $into->id, $counts);
    }
}
