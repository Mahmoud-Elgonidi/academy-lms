<?php

namespace Modules\People;

use Modules\People\Actions\LinkGuardian;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;

final class PeopleService implements PeopleApi
{
    public function __construct(
        private readonly LinkGuardian $linkGuardian,
        private readonly SaveTeacherProfile $saveTeacherProfile,
    ) {}

    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void
    {
        $this->linkGuardian->handle($guardianId, $studentId, $relationship, $isPrimary);
    }

    public function saveTeacherProfile(TeacherProfileData $profile): void
    {
        $this->saveTeacherProfile->handle($profile);
    }
}
