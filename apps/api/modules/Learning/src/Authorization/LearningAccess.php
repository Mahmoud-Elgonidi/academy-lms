<?php

namespace Modules\Learning\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Who sees a student's learning: the student, their linked guardians, the
 * teacher who teaches them, and staff who oversee reports. Internal notes
 * stay with the teacher and staff.
 */
final class LearningAccess
{
    public function __construct(private readonly PeopleApi $people) {}

    /** @return list<string> the students this person follows as a learner or a guardian */
    public function learnersOf(Authenticatable $user): array
    {
        $id = (string) $user->getAuthIdentifier();

        return array_values(array_unique([$id, ...$this->people->studentsOf($id)]));
    }

    public function isStaff(Authenticatable $user): bool
    {
        return Gate::forUser($user)->any([Ability::ReviewReports->value, Ability::ViewEnrollments->value]);
    }

    public function canSeeEnrollment(Authenticatable $user, EnrollmentData $enrollment): bool
    {
        return $enrollment->teacherId === $user->getAuthIdentifier()
            || in_array($enrollment->studentId, $this->learnersOf($user), true)
            || $this->isStaff($user);
    }

    public function canSeeHomework(Authenticatable $user, Homework $homework): bool
    {
        return $homework->teacher_id === $user->getAuthIdentifier()
            || in_array($homework->student_id, $this->learnersOf($user), true)
            || $this->isStaff($user);
    }

    public function canHandIn(Authenticatable $user, Homework $homework): bool
    {
        return in_array($homework->student_id, $this->learnersOf($user), true);
    }

    public function writesReport(Authenticatable $user, SessionReport $report): bool
    {
        return $report->teacher_id === $user->getAuthIdentifier();
    }

    public function marksHomework(Authenticatable $user, Homework $homework): bool
    {
        return $homework->teacher_id === $user->getAuthIdentifier();
    }
}
