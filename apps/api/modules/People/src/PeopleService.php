<?php

namespace Modules\People;

use Modules\People\Actions\LinkGuardian;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\GuardianStudent;

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

    public function guardiansOf(string $studentId, bool $reportsOnly = false): array
    {
        return GuardianStudent::query()->where('student_id', $studentId)
            ->when($reportsOnly, fn ($q) => $q->where('receives_reports', true))
            ->pluck('guardian_id')->all();
    }

    public function studentsOf(string $guardianId): array
    {
        return GuardianStudent::query()->where('guardian_id', $guardianId)->pluck('student_id')->all();
    }
}
