<?php

namespace Modules\Enrollment\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A learner's courses (subject, teacher, since when, how they ended), and
 * the ones a teacher teaches (without the students' names). A course still
 * running holds an erasure back: the academy ends it first, which settles
 * its lessons and money. Nothing is erased here: courses are the academy's
 * record of what was sold and taught.
 */
final class CoursesData implements PersonalDataSource
{
    private const RUNNING = ['pending_payment', 'active', 'paused'];

    public function key(): string
    {
        return 'courses';
    }

    public function export(string $userId): PersonalDataPart
    {
        $base = fn () => DB::table('enrollments as e')
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 'e.academy_id')->on('sub.id', '=', 'e.subject_id'))
            ->orderBy('e.start_date');

        return new PersonalDataPart([
            'as_student' => $base()->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'e.academy_id')->on('t.id', '=', 'e.teacher_id'))
                ->where('e.student_id', $userId)
                ->get([DB::raw('coalesce(sub.name_ar, sub.name_en) as subject'), 't.name as teacher', 'e.status', 'e.start_date', 'e.ended_at'])->map(fn (object $r) => (array) $r)->all(),
            'as_teacher' => $base()->where('e.teacher_id', $userId)
                ->get([DB::raw('coalesce(sub.name_ar, sub.name_en) as subject'), 'e.status', 'e.start_date', 'e.ended_at'])->map(fn (object $r) => (array) $r)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        $running = DB::table('enrollments')->whereIn('status', self::RUNNING)
            ->where(fn ($q) => $q->where('student_id', $userId)->orWhere('teacher_id', $userId))->count();

        return $running === 0 ? [] : [trans_choice('enrollment::privacy.courses_running', $running, ['count' => $running])];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
