<?php

namespace Modules\SharedKernel\Plans;

/**
 * What an academy's SaaS plan counts. Teachers and active students are held
 * at the door (a new one is refused at the limit); video minutes (a calendar
 * month on the academy's clock) and storage are watched: the academy and the
 * platform team are told, and nothing in progress is ever cut. The AI
 * helpers' uses (a calendar month too) stop the helpers at the limit, and the
 * work goes on by hand. A monthly limit may grow for the month with an extra
 * pack the platform team activates.
 */
enum Limit: string
{
    case Teachers = 'teachers';
    case ActiveStudents = 'active_students';
    case VideoMinutes = 'video_minutes';
    case StorageMb = 'storage_mb';
    case AiUses = 'ai_uses';

    /** Refused at the limit, rather than only watched. */
    public function held(): bool
    {
        return $this === self::Teachers || $this === self::ActiveStudents;
    }

    /** Counted afresh each calendar month. */
    public function monthly(): bool
    {
        return $this === self::VideoMinutes || $this === self::AiUses;
    }
}
