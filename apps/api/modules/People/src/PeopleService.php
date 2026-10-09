<?php

namespace Modules\People;

use Carbon\CarbonImmutable;
use Modules\People\Actions\Checks\PauseTeacherForMinors;
use Modules\People\Actions\Checks\ResumeTeacherForMinors;
use Modules\People\Actions\LinkGuardian;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Actions\SaveStudentProfile;
use Modules\People\Actions\SaveTeacherProfile;
use Modules\People\Actions\SendWelcome;
use Modules\People\Checks\Clearances;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Contracts\DTOs\StudentDetails;
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
        private readonly ConsentRules $consents,
        private readonly Clearances $clearances,
    ) {}

    /** Under this age a student is a minor (safeguarding; the consent age is the academy's own). */
    public const ADULT_AGE = 18;

    public function minors(array $studentIds): array
    {
        return $this->consents->under(array_values(array_unique($studentIds)), self::ADULT_AGE);
    }

    public function isMinor(string $studentId): bool
    {
        return $this->minors([$studentId])[$studentId] ?? false;
    }

    public function clearances(array $teacherIds): array
    {
        return $this->clearances->many($teacherIds);
    }

    public function mayTeach(string $teacherId, string $studentId): bool
    {
        return ! $this->isMinor($studentId) || $this->clearances->of($teacherId)->cleared;
    }

    public function pauseForMinors(string $teacherId, string $reason, string $actorId): void
    {
        app(PauseTeacherForMinors::class)->handle($teacherId, $reason, $actorId);
    }

    public function resumeForMinors(string $teacherId, string $reason, string $actorId): void
    {
        app(ResumeTeacherForMinors::class)->handle($teacherId, $reason, $actorId);
    }

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

    public function studentDetails(string $studentId): StudentDetails
    {
        $profile = StudentProfile::query()->find($studentId);

        return new StudentDetails($profile?->gender, $profile?->country, $profile?->school_grade, $profile?->teacher_gender);
    }

    public function saveStudentDetails(string $studentId, StudentDetails $details): void
    {
        $now = $this->studentDetails($studentId);
        app(SaveStudentDetails::class)->handle($studentId, new StudentDetails(
            $details->gender ?? $now->gender, $details->country ?? $now->country, $details->schoolGrade ?? $now->schoolGrade, $details->teacherGender ?? $now->teacherGender,
        ));
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

    public function sendWelcome(array $userIds): void
    {
        app(SendWelcome::class)->handle($userIds);
    }

    public function consentState(string $studentId): ConsentState
    {
        return $this->consents->state($studentId);
    }
}
