<?php

namespace Modules\People\Contracts;

use Modules\People\Contracts\DTOs\TeacherProfileData;

/** What other modules may ask of People. */
interface PeopleApi
{
    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void;

    public function saveTeacherProfile(TeacherProfileData $profile): void;

    /** @return list<string> ids of the student's guardians; with $reportsOnly, those who asked for reports */
    public function guardiansOf(string $studentId, bool $reportsOnly = false): array;

    /** @return list<string> ids of the students this guardian is linked to */
    public function studentsOf(string $guardianId): array;
}
