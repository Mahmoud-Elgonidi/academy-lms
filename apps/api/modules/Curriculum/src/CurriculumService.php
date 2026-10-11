<?php

namespace Modules\Curriculum;

use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\ProgramOption;
use Modules\Curriculum\Contracts\DTOs\ResourceFile;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;

final class CurriculumService implements CurriculumApi
{
    public function programsFor(string $subjectId): array
    {
        return Program::query()->where('subject_id', $subjectId)->where('status', 'active')->orderBy('name_ar')->get()
            ->map(fn (Program $p) => $this->option($p))
            ->filter(fn (ProgramOption $o) => $o->publishedVersionId !== null)
            ->values()->all();
    }

    public function program(string $programId): ?ProgramOption
    {
        $program = Program::query()->find($programId);

        return $program ? $this->option($program) : null;
    }

    public function tree(string $versionId): CurriculumTree
    {
        return new CurriculumTree($versionId, Node::query()->where('version_id', $versionId)->get()->map(fn (Node $n) => $n->toData())->all());
    }

    public function resources(array $nodeIds, bool $withTeachersOnly): array
    {
        if ($nodeIds === []) {
            return [];
        }

        return NodeResource::query()->whereIn('node_id', array_values(array_unique($nodeIds)))
            ->when(! $withTeachersOnly, fn ($q) => $q->where('audience', 'all'))
            ->orderBy('ordinal')->get()
            ->groupBy('node_id')
            ->map(fn ($list) => $list->map(fn (NodeResource $r) => $r->toData())->values()->all())
            ->all();
    }

    public function resourceFile(string $resourceId): ?ResourceFile
    {
        $r = NodeResource::query()->where('kind', 'file')->find($resourceId);

        return $r === null ? null : new ResourceFile($r->id, NodeResource::DISK, (string) $r->file_path, (string) $r->file_name, (string) $r->mime, (int) $r->size, $r->audience);
    }

    public function practiceItems(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }

        return NodeItem::query()->whereIn('node_id', array_values(array_unique($nodeIds)))->orderBy('ordinal')->get()
            ->groupBy('node_id')
            ->map(fn ($list) => $list->map(fn (NodeItem $item) => $item->toData())->values()->all())
            ->all();
    }

    private function option(Program $program): ProgramOption
    {
        $published = $program->versions()->where('status', 'published')->value('id');

        return new ProgramOption(
            $program->id,
            $program->subject_id,
            $program->name(),
            $published,
            $published ? Node::query()->where('version_id', $published)->where('kind', 'lesson')->count() : 0,
        );
    }
}
