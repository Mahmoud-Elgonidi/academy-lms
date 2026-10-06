<?php

namespace Modules\People\Contracts;

use Modules\People\Contracts\DTOs\TeacherProfileData;

/** What other modules may ask of People. */
interface PeopleApi
{
    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void;

    public function saveTeacherProfile(TeacherProfileData $profile): void;
}
