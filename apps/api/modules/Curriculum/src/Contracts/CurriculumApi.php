<?php

namespace Modules\Curriculum\Contracts;

use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Contracts\DTOs\ProgramOption;

/** What other modules may ask of Curriculum. */
interface CurriculumApi
{
    /** @return list<ProgramOption> active programs of a subject that have a published version */
    public function programsFor(string $subjectId): array;

    public function program(string $programId): ?ProgramOption;

    /** The version's tree; empty when it does not exist. */
    public function tree(string $versionId): CurriculumTree;
}
