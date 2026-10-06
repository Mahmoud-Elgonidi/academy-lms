<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Modules\Scheduling\Domain\Availability\StudentPreference;

/** A coordinator's search on "Find a teacher". */
final readonly class TeacherSearch
{
    public const GENDERS = ['any', 'female_only', 'male_only', 'prefer_female', 'prefer_male'];

    public function __construct(
        public string $subjectId,
        public StudentPreference $preference,
        public string $gender = 'any',
        public ?string $language = null,
        public bool $forTrial = false,
        public ?string $searcherId = null,
    ) {}
}
