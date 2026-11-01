<?php

namespace Modules\Quran\Exams;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Quran\Domain\Exams\Examiners;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Whether someone may examine this exam (Domain\Examiners, by the academy's
 * choice): who holds quran.examine, or under "another teacher" or "any
 * teacher" a teacher of the Quran, read from Identity's active accounts;
 * the course's teacher (a group's too) only under "any teacher".
 */
final class ExaminerCheck
{
    public function __construct(
        private readonly IdentityApi $identity,
        private readonly EnrollmentApi $enrollments,
    ) {}

    public function assert(QuranExam $exam, string $examinerId): void
    {
        $why = Examiners::refusal(
            QuranSettings::current()->examiner,
            in_array($examinerId, $this->identity->usersWithAbility(Ability::ExamineQuran), true),
            in_array($examinerId, $this->identity->usersWithAbility(Ability::TeachQuran), true),
            in_array($examinerId, $this->courseTeachers($exam), true),
            $examinerId === $exam->student_id,
        );
        if ($why !== null) {
            throw ValidationException::withMessages(['examiner_id' => __('quran::exams.errors.examiner_'.$why)]);
        }
    }

    /** @return list<string> the course's teacher, and its group's when the plan is a group's */
    private function courseTeachers(QuranExam $exam): array
    {
        $teachers = [];
        if ($exam->enrollment_id !== null && ($course = $this->enrollments->find($exam->enrollment_id)) !== null) {
            $teachers[] = $course->teacherId;
        }
        $group = $exam->plan?->group_id;
        if ($group !== null && ($teacher = DB::table('lesson_groups')->where('id', $group)->value('teacher_id')) !== null) {
            $teachers[] = (string) $teacher;
        }

        return $teachers;
    }
}
