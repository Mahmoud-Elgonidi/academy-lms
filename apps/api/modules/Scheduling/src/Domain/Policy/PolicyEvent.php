<?php

namespace Modules\Scheduling\Domain\Policy;

/** What happened to a session, the first thing a rule looks at. */
enum PolicyEvent: string
{
    case StudentReschedule = 'student_reschedule';
    case AcademyReschedule = 'academy_reschedule';
    case StudentCancel = 'student_cancel';
    case TeacherCancel = 'teacher_cancel';
    case AcademyCancel = 'academy_cancel';
    case Closure = 'closure';
    case Emergency = 'emergency';
    case Unpaid = 'unpaid';
    case StudentAbsent = 'student_absent';
    case TeacherAbsent = 'teacher_absent';

    public function isReschedule(): bool
    {
        return in_array($this, [self::StudentReschedule, self::AcademyReschedule], true);
    }
}
