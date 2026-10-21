<?php

namespace Modules\People\Contracts\DTOs;

/**
 * A teacher as the academy's public page shows them: the card they wrote
 * and what their profile says they teach. Never a number, an email, their
 * account, pay, KPIs, vetting or anything the academy keeps to itself.
 */
final readonly class PublicTeacher
{
    /**
     * @param  string  $id  the card's own id (not the teacher's account)
     * @param  ?string  $gender  female | male
     * @param  list<string>  $languages  ISO 639-1 codes they explain in
     * @param  list<string>  $ageGroups  AgeGroup values they teach (none: any age)
     * @param  list<PublicTeacherSubject>  $subjects
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public ?string $bio,
        public ?string $photoUrl,
        public ?string $gender,
        public array $languages,
        public array $ageGroups,
        public array $subjects,
        public bool $acceptsTrials,
    ) {}
}
