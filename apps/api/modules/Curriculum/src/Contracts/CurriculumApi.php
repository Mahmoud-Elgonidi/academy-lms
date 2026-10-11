<?php

namespace Modules\Curriculum\Contracts;

use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\LessonResource;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Curriculum\Contracts\DTOs\ProgramOption;
use Modules\Curriculum\Contracts\DTOs\ResourceFile;

/** What other modules may ask of Curriculum. */
interface CurriculumApi
{
    /** @return list<ProgramOption> active programs of a subject that have a published version */
    public function programsFor(string $subjectId): array;

    public function program(string $programId): ?ProgramOption;

    /** The version's tree; empty when it does not exist. */
    public function tree(string $versionId): CurriculumTree;

    /**
     * The files and links on these lessons, in their order; the teachers-only ones too only when asked.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, list<LessonResource>> by lesson
     */
    public function resources(array $nodeIds, bool $withTeachersOnly): array;

    /** Where a lesson's file is kept, for whoever serves it; null for a link or one that is gone. */
    public function resourceFile(string $resourceId): ?ResourceFile;

    /**
     * The practice items on these lessons, in their order (what games play).
     *
     * @param  list<string>  $nodeIds
     * @return array<string, list<PracticeItem>> by lesson
     */
    public function practiceItems(array $nodeIds): array;
}
