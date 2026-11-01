<?php

namespace Modules\Quran\Domain\Exams;

/**
 * Who may examine a learner (docs/architecture/02, section 26), by the
 * academy's choice (quran_settings.examiner):
 *
 * - supervisor: only who holds quran.examine (owner, admin, supervisor);
 * - other_teacher (the default): them, or a teacher of the Quran, but never
 *   the course's own teacher;
 * - any_teacher: them, or any teacher of the Quran, the course's own too.
 *
 * Never the learner themself.
 */
final class Examiners
{
    public const SETTINGS = ['other_teacher', 'supervisor', 'any_teacher'];

    /** Why this person may not examine: own_teacher, not_examiner or student; null when they may. */
    public static function refusal(string $setting, bool $examines, bool $teachesQuran, bool $isCourseTeacher, bool $isStudent): ?string
    {
        return match (true) {
            $isStudent => 'student',
            $isCourseTeacher && $setting !== 'any_teacher' => 'own_teacher',
            $examines, $setting !== 'supervisor' && $teachesQuran => null,
            default => 'not_examiner',
        };
    }
}
