<?php

namespace Modules\Scheduling\Domain\Enums;

/** A teacher's leave: asked for, then decided; the teacher may take it back while it waits, a manager at any time before it ends. */
enum TimeOffStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [self::Withdrawn],
            self::Rejected, self::Withdrawn => [],
        }, true);
    }

    /** Holds the hours: nobody can ask for them twice. */
    public function holds(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
