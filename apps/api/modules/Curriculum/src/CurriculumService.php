<?php

namespace Modules\Curriculum;

use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\ProgramOption;
use Modules\Curriculum\Models\Node;
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
