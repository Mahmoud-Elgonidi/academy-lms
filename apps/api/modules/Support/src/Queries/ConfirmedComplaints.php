<?php

namespace Modules\Support\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\Contracts\TeacherComplaints;

/**
 * Complaints the academy confirmed, per teacher, by when they were made
 * (Evaluation's complaints KPI: confirmed complaints per 100 lessons). A
 * complaint dismissed later stops counting.
 */
final class ConfirmedComplaints implements TeacherComplaints
{
    public function forTeachers(array $teacherIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($teacherIds === []) {
            return [];
        }

        return DB::table('tickets')->where('kind', 'complaint')->where('complaint_status', 'validated')
            ->whereIn('teacher_id', $teacherIds)->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->groupBy('teacher_id')->selectRaw('teacher_id, count(*) as n')->pluck('n', 'teacher_id')
            ->map(fn ($n) => (int) $n)->all();
    }
}
