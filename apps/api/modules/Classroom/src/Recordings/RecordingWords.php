<?php

namespace Modules\Classroom\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Models\Recording;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerClock;

/**
 * A recording's lesson in a few words, for the activity log: "Yousef Khaled
 * with Nour · 5 October 2026 4:00 PM". Kept as written and read by anyone
 * later, so on the academy's clock and zone, not the person's who acted.
 */
final class RecordingWords
{
    public static function lesson(Recording $recording): string
    {
        $row = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.id', '=', 's.teacher_id')->on('t.academy_id', '=', 's.academy_id'))
            ->where('s.id', $recording->session_id)
            // A group lesson's students as its first and how many more.
            ->first(['s.starts_at', 't.name as teacher', DB::raw("(select min(u.name) || case when count(*) > 1 then ' +' || (count(*) - 1) else '' end
                from session_participants p join users u on u.id = p.user_id and u.academy_id = p.academy_id
                where p.session_id = s.id and p.academy_id = s.academy_id and p.role = 'student') as student")]);
        if ($row === null) {
            return '';
        }
        $tz = app(TenantContext::class)->require()->defaultTimezone;
        $when = CarbonImmutable::parse($row->starts_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j F Y H:i', app(ViewerClock::class)->academy()));

        return __('classroom::recordings.lesson_words', ['student' => $row->student ?? '—', 'teacher' => $row->teacher, 'when' => $when]);
    }
}
