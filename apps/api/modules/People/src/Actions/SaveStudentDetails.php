<?php

namespace Modules\People\Actions;

use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Models\StudentProfile;

/** A student's gender, country and school grade, and who should teach them ("Find a teacher" starts from it). */
final class SaveStudentDetails
{
    public const GRADES = ['kg1', 'kg2', 'g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8', 'g9', 'g10', 'g11', 'g12', 'university'];

    public const TEACHER_GENDERS = ['female_only', 'male_only', 'prefer_female', 'prefer_male'];

    public function handle(string $studentId, StudentDetails $details): StudentProfile
    {
        $e = 'people::people.errors.';
        $country = $details->country === null || $details->country === '' ? null : strtoupper($details->country);
        match (true) {
            $details->gender !== null && ! in_array($details->gender, ['female', 'male'], true) => throw ValidationException::withMessages(['gender' => __($e.'student_gender')]),
            $country !== null && ! preg_match('/^[A-Z]{2}$/', $country) => throw ValidationException::withMessages(['country' => __($e.'country')]),
            $details->schoolGrade !== null && ! in_array($details->schoolGrade, self::GRADES, true) => throw ValidationException::withMessages(['school_grade' => __($e.'grade')]),
            $details->teacherGender !== null && ! in_array($details->teacherGender, self::TEACHER_GENDERS, true) => throw ValidationException::withMessages(['teacher_gender' => __($e.'teacher_gender')]),
            default => null,
        };

        return StudentProfile::query()->updateOrCreate(['user_id' => $studentId], [
            'gender' => $details->gender,
            'country' => $country,
            'school_grade' => $details->schoolGrade,
            'teacher_gender' => $details->teacherGender,
        ]);
    }
}
