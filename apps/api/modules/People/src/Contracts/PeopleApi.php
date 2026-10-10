<?php

namespace Modules\People\Contracts;

use Carbon\CarbonImmutable;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\StudentProfileData;
use Modules\People\Contracts\DTOs\TeacherClearance;
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

    /** A student's birth date, and the look of their area when the academy wants other than their age gives (null: by age). */
    public function saveStudentProfile(string $studentId, ?CarbonImmutable $birthDate, ?LearnerStage $stage): void;

    public function studentProfile(string $studentId, ?CarbonImmutable $today = null): StudentProfileData;

    /** A student's gender, country, school grade, and who should teach them ("Find a teacher" starts from it). */
    public function studentDetails(string $studentId): StudentDetails;

    /** Saves those; a detail left null keeps what the student has. */
    public function saveStudentDetails(string $studentId, StudentDetails $details): void;

    /** Sets those exactly as given (null clears one): a correction by the student or their guardian. */
    public function correctStudentDetails(string $studentId, StudentDetails $details): void;

    /**
     * What each of those may be, named in the current language (for a form):
     * gender, country (ISO codes), school_grade and teacher_gender ("" is either).
     *
     * @return array{gender: array<string, string>, country: array<string, string>, school_grade: array<string, string>, teacher_gender: array<string, string>}
     */
    public function studentDetailChoices(): array;

    /**
     * Sends each person the academy made an account for (after commit) their
     * own link to choose a password; who already has one, and a child under a
     * parent, get none.
     *
     * @param  list<string>  $userIds
     */
    public function sendWelcome(array $userIds): void;

    /** Whether a student's account needs a guardian's consent, and whether one stands (the child's own links and sign-in wait for it). */
    public function consentState(string $studentId): ConsentState;

    /**
     * Which of these students are under 18: by birth date on the academy's
     * calendar; with none, managed for a guardian or with a guardian at all.
     *
     * @param  list<string>  $studentIds
     * @return array<string, bool>
     */
    public function minors(array $studentIds): array;

    public function isMinor(string $studentId): bool;

    /**
     * Whether each teacher may take new lessons with students under 18: not
     * paused for a safeguarding concern and, when the academy asks for it,
     * with a verified criminal record check that has not run out.
     *
     * @param  list<string>  $teacherIds
     * @return array<string, TeacherClearance>
     */
    public function clearances(array $teacherIds): array;

    /** A teacher may take a new lesson with this student (always, with an adult). */
    public function mayTeach(string $teacherId, string $studentId): bool;

    /** Keeps a teacher from new lessons with minors while a safeguarding concern is looked into (the academy's reason, never shown to them). */
    public function pauseForMinors(string $teacherId, string $reason, string $actorId): void;

    public function resumeForMinors(string $teacherId, string $reason, string $actorId): void;
}
