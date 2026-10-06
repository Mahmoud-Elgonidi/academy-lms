<?php

namespace Modules\People\Contracts\DTOs;

final readonly class TeacherProfileData
{
    /**
     * @param  list<string>  $languages  ISO 639-1 codes they explain in
     * @param  list<string>  $subjectIds
     */
    public function __construct(
        public string $teacherId,
        public string $gender,
        public array $languages = ['ar'],
        public array $subjectIds = [],
        public ?string $dialect = null,
        public bool $acceptsNew = true,
        public bool $acceptsTrials = true,
        public ?int $maxWeeklyMinutes = null,
    ) {}
}
