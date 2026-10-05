<?php

namespace Modules\Scheduling\Domain\Enums;

/** Session lifecycle (docs/architecture/02-domain-rules.md, section 5). */
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case StudentAbsent = 'student_absent';
    case TeacherAbsent = 'teacher_absent';
    case NeedsReview = 'needs_review';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Scheduled => [self::Scheduled, self::Cancelled, self::TeacherAbsent, self::NeedsReview, self::InProgress],
            self::InProgress => [self::StudentAbsent, self::Completed, self::NeedsReview],
            self::NeedsReview => [self::Completed, self::Cancelled, self::StudentAbsent, self::TeacherAbsent],
            self::Completed, self::StudentAbsent, self::TeacherAbsent => [self::NeedsReview],
            self::Cancelled => [],
        }, true);
    }

    /** Still open: the live board and the sweeper act on these. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Scheduled, self::InProgress], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::StudentAbsent, self::TeacherAbsent, self::Cancelled], true);
    }
}
