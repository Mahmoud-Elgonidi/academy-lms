<?php

namespace Modules\Scheduling\Domain\LiveBoard;

/** Why a session needs someone now. */
enum BoardIssue: string
{
    case TeacherNotJoined = 'teacher_not_joined';
    case NobodyJoined = 'nobody_joined';
    case StudentNotJoined = 'student_not_joined';
    case WeakConnection = 'weak_connection';
    case Unfunded = 'unfunded';
    case NeedsReview = 'needs_review';

    /** Higher first. */
    public function severity(): int
    {
        return match ($this) {
            self::TeacherNotJoined, self::NobodyJoined, self::StudentNotJoined => 3,
            self::NeedsReview => 2,
            self::WeakConnection, self::Unfunded => 1,
        };
    }
}
