<?php

namespace Modules\SharedKernel\Time;

/**
 * The line under a page's title that says which clock its times are on:
 * "المواعيد هنا بتوقيتك: القاهرة (UTC+2)." Every page with times says it.
 */
final class ZoneHint
{
    /** Times written on the viewer's zone. */
    public static function yours(): string
    {
        return __('shared::time.hint.yours', ['zone' => self::viewer()]);
    }

    /** Hours kept on each teacher's own clock (shifts), the teacher's zone beside their name. */
    public static function teachers(): string
    {
        return __('shared::time.hint.teacher', ['zone' => self::viewer()]);
    }

    /** Lessons on the viewer's zone, the teacher's beside them. */
    public static function lessonsWithTeacher(): string
    {
        return __('shared::time.hint.lessons_teacher', ['zone' => self::viewer()]);
    }

    /** Days and times on the academy's zone. */
    public static function academy(string $timezone): string
    {
        return __('shared::time.hint.academy', ['zone' => Timezones::label($timezone)]);
    }

    private static function viewer(): string
    {
        return Timezones::label(app(ViewerTimezone::class)->effective());
    }
}
