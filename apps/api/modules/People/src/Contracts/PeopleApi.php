<?php

namespace Modules\People\Contracts;

use Carbon\CarbonImmutable;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Contracts\DTOs\PublicTeacher;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\StudentProfileData;
use Modules\People\Contracts\DTOs\TeacherCardSummary;
use Modules\People\Contracts\DTOs\TeacherClearance;
use Modules\People\Contracts\DTOs\TeacherProfileData;

/** What other modules may ask of People. */
interface PeopleApi
{
    /**
     * Links a guardian to a student. A child waiting for consent has the new
     * guardian asked for it, unless $askConsent is false (a free trial, which
     * the guardian brings the child to by their own link: the consent is
     * asked when it is first needed, requestConsent()).
     */
    public function linkGuardian(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true, bool $askConsent = true): void;

    /**
     * Asks a child's guardians for their consent now, when the child's account
     * waits for it (the child's own sign-in or seat, an enrolment); nothing
     * when it does not. Each guardian hears it at most once a day.
     *
     * @return int how many guardians are asked
     */
    public function requestConsent(string $studentId): int;

    public function saveTeacherProfile(TeacherProfileData $profile): void;

    /** The most students one of this teacher's group lessons may hold; null when they take no groups. */
    public function groupCapacity(string $teacherId): ?int;

    /**
     * groupCapacity() for many teachers in one read (Find a teacher's group mode).
     *
     * @param  list<string>  $teacherIds
     * @return array<string, int> by teacher id; one who takes no groups is left out
     */
    public function groupCapacities(array $teacherIds): array;

    /** The most students any active teacher takes in a group lesson (the academy's page says "up to N"); null when none teaches groups. */
    public function largestGroupSize(): ?int;

    /** @return list<string> ids of the student's guardians; with $reportsOnly, those who asked for reports */
    public function guardiansOf(string $studentId, bool $reportsOnly = false): array;

    /** @return list<string> ids of the students this guardian is linked to */
    public function studentsOf(string $guardianId): array;

    /** A student's birth date, and the look of their area when the academy wants other than their age gives (null: by age). */
    public function saveStudentProfile(string $studentId, ?CarbonImmutable $birthDate, ?LearnerStage $stage): void;

    public function studentProfile(string $studentId, ?CarbonImmutable $today = null): StudentProfileData;

    /**
     * The look of each of these students' areas (the one chosen, or the one
     * their age gives), in one read: studentProfile()'s stage for many.
     *
     * @param  list<string>  $studentIds
     * @return array<string, LearnerStage>
     */
    public function stages(array $studentIds, ?CarbonImmutable $today = null): array;

    /** A student's gender, country, school grade, and who should teach them ("Find a teacher" starts from it). */
    public function studentDetails(string $studentId): StudentDetails;

    /** Saves those; a detail left null keeps what the student has. */
    public function saveStudentDetails(string $studentId, StudentDetails $details): void;

    /** Sets those exactly as given (null clears one): a correction by the student or their guardian. */
    public function correctStudentDetails(string $studentId, StudentDetails $details): void;

    /**
     * The country a person lives in, as the academy knows it: a student's
     * own (their details), else the one they have as a guardian; null when
     * neither says (ISO 3166-1 alpha-2).
     */
    public function countryOf(string $userId): ?string;

    /**
     * Gives a person a country known elsewhere (a lead's), only where they
     * have none: on their student details when they study here, and as a
     * guardian when they are one. Never changes a country already there.
     *
     * @return int how many profiles took it (0: nothing to fill)
     */
    public function fillCountry(string $userId, ?string $country): int;

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
     * consentState() for many students in one read (who is still waiting for
     * a guardian, to leave them out of what reaches the child themselves).
     *
     * @param  list<string>  $studentIds
     * @return array<string, ConsentState>
     */
    public function consentStates(array $studentIds): array;

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

    /**
     * The teachers on the academy's public page: the cards they switched on
     * and the academy left on, of teachers who teach now, with what their
     * profile says they teach. Never a number, an email or their account.
     *
     * @return list<PublicTeacher>
     */
    public function publicTeachers(): array;

    /** One teacher's card, when it is on the academy's page (the same rules as publicTeachers); null otherwise. */
    public function publicTeacherOf(string $teacherId): ?PublicTeacher;

    /** @return list<TeacherCardSummary> every card the academy's teachers made, for its page settings */
    public function teacherCards(): array;

    /** Takes a teacher's card off the academy's page, or puts it back (site.manage). */
    public function hideTeacherCard(string $cardId, bool $hidden): void;
}
