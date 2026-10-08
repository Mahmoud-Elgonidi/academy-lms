<?php

namespace Modules\Classroom\Recordings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Models\Recording;
use Modules\SharedKernel\Tenancy\TenantContext;

/** A recording's lesson in a few words, for the activity log: "Yousef Khaled with Nour · 5 October 2026 16:00". */
final class RecordingWords
{
    public static function lesson(Recording $recording): string
    {
        $row = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.id', '=', 's.teacher_id')->on('t.academy_id', '=', 's.academy_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.session_id', '=', 's.id')->on('p.academy_id', '=', 's.academy_id')->where('p.role', 'student'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'p.user_id')->on('u.academy_id', '=', 'p.academy_id'))
            ->where('s.id', $recording->session_id)
            ->first(['s.starts_at', 't.name as teacher', 'u.name as student']);
        if ($row === null) {
            return '';
        }
        $tz = app(TenantContext::class)->require()->defaultTimezone;
        $when = CarbonImmutable::parse($row->starts_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y H:i');

        return __('classroom::recordings.lesson_words', ['student' => $row->student ?? '—', 'teacher' => $row->teacher, 'when' => $when]);
    }
}
