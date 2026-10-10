<?php

namespace Modules\Privacy\Queries;

use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Subjects;

/**
 * What a person may put right on their own, and for whom: their name, and
 * for a student (themselves as an adult student, or a child they follow as a
 * guardian) gender, country, school grade and who should teach them. A
 * teacher corrects their name; the rest of a teacher's profile is the
 * academy's. Children and younger students ask their guardian (PrivacyRules).
 * Anything else goes through a correction request.
 */
final class EditableDetails
{
    public const STUDENT_FIELDS = ['gender', 'country', 'school_grade', 'teacher_gender'];

    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly FamilyView $family,
        private readonly PeopleApi $people,
    ) {}

    /** @return list<string> the fields $side may correct for this subject */
    public function fields(string $subjectId, string $side): array
    {
        return $side !== 'teacher' && $this->isStudent($subjectId) ? ['name', ...self::STUDENT_FIELDS] : ['name'];
    }

    /**
     * @return list<array{id: string, name: string, self: bool, student: bool, fields: list<string>, values: array<string, ?string>}>
     *                                                                                                                                each person the viewer may correct, with what they hold now
     */
    public function forViewer(string $viewerId, string $viewerName, string $side): array
    {
        $out = [];
        foreach ($this->rules->subjectsFor($viewerId, $viewerName, $side) as $subject) {
            if (Subjects::isErased($subject['id'])) {
                continue;
            }
            $fields = $this->fields($subject['id'], $side);
            $student = in_array('gender', $fields, true);
            $details = $student ? $this->people->studentDetails($subject['id']) : null;
            $out[] = $subject + [
                'student' => $student,
                'fields' => $fields,
                'values' => [
                    'name' => Subjects::name($subject['id']),
                    'gender' => $details?->gender,
                    'country' => $details?->country,
                    'school_grade' => $details?->schoolGrade,
                    'teacher_gender' => $details?->teacherGender,
                ],
            ];
        }

        return $out;
    }

    private function isStudent(string $userId): bool
    {
        foreach ($this->family->learners($userId) as $learner) {
            if ($learner->id === $userId) {
                return true;
            }
        }

        return false;
    }
}
