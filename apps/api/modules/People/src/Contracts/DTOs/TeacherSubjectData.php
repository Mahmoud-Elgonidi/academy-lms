<?php

namespace Modules\People\Contracts\DTOs;

/** A subject a teacher teaches: the levels, from one to another (null: open), and the categories they teach in (none: any). */
final readonly class TeacherSubjectData
{
    /** @param  list<string>  $categoryIds */
    public function __construct(
        public string $subjectId,
        public ?string $levelFromId = null,
        public ?string $levelToId = null,
        public array $categoryIds = [],
    ) {}
}
