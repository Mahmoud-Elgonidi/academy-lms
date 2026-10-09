<?php

namespace Modules\People\Contracts\DTOs;

final readonly class TeacherProfileData
{
    /**
     * @param  list<string>  $languages  ISO 639-1 codes they explain in
     * @param  list<string>  $subjectIds  the subjects they teach; one also in $subjects takes its levels and categories from there, one only here keeps what it had
     * @param  list<string>  $ageGroups  AgeGroup values; null keeps what the profile has (none: any age)
     * @param  list<TeacherSubjectData>  $subjects
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
        public ?array $ageGroups = null,
        public ?int $maxDailyLessons = null,
        public array $subjects = [],
    ) {}

    /** The same profile for a teacher whose account was just made. */
    public function forTeacher(string $teacherId): self
    {
        return new self($teacherId, $this->gender, $this->languages, $this->subjectIds, $this->dialect, $this->acceptsNew,
            $this->acceptsTrials, $this->maxWeeklyMinutes, $this->ageGroups, $this->maxDailyLessons, $this->subjects);
    }
}
