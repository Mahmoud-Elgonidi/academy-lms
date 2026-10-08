<?php

namespace Modules\SharedKernel\Plans;

/**
 * What an academy's SaaS plan counts. Teachers and active students are held
 * at the door (a new one is refused at the limit); video minutes (a calendar
 * month on the academy's clock) and storage are watched: the academy and the
 * platform team are told, and nothing in progress is ever cut.
 */
enum Limit: string
{
    case Teachers = 'teachers';
    case ActiveStudents = 'active_students';
    case VideoMinutes = 'video_minutes';
    case StorageMb = 'storage_mb';

    /** Refused at the limit, rather than only watched. */
    public function held(): bool
    {
        return $this === self::Teachers || $this === self::ActiveStudents;
    }

    /** Counted afresh each calendar month. */
    public function monthly(): bool
    {
        return $this === self::VideoMinutes;
    }
}
