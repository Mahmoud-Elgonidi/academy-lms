<?php

namespace Modules\People;

use Carbon\CarbonImmutable;
use Modules\People\Actions\LinkGuardian;
use Modules\People\Actions\SaveStudentProfile;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Contracts\DTOs\StudentProfileData;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\GuardianStudent;
use Modules\People\Models\StudentProfile;

final class PeopleService implements PeopleApi
{
    public function __construct(
        private readonly LinkGuardian $linkGuardian,
        private readonly SaveTeacherProfile $saveTeacherProfile,
        private readonly SaveStudentProfile $saveStudentProfile,
    ) {}

    public function saveStudentProfile(string $studentId, ?CarbonImmutable $birthDate, ?LearnerStage $stage): void
    {
        $this->saveStudentProfile->handle($studentId, $birthDate, $stage);
    }

    public function studentProfile(string $studentId, ?CarbonImmutable $today = null): StudentProfileData
    {
        $profile = StudentProfile::query()->find($studentId);
        $age = $profile?->birth_date ? (int) $profile->birth_date->diffInYears($today ?? CarbonImmutable::now()) : null;
        $chosen = $profile?->stage ? LearnerStage::from($profile->stage) : null;

        return new StudentProfileData($studentId, $profile?->birth_date, $chosen, $chosen ?? LearnerStage::forAge($age), $age);
    }

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
