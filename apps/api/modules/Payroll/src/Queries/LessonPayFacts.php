<?php

namespace Modules\Payroll\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** What pays a lesson, read from Scheduling's table (reads may join across modules). */
final class LessonPayFacts
{
    /** Lessons that are over one way or another. */
    public const FINAL = ['completed', 'student_absent', 'teacher_absent', 'cancelled'];

    /** @return object{id: string, status: string, type: string, teacher_id: string, subject_id: string, starts_at: CarbonImmutable, booked_min: int, decision: ?array<string, mixed>}|null */
    public function find(string $sessionId): ?object
    {
        $row = DB::table('sessions')->where('id', $sessionId)->first(['id', 'status', 'type', 'teacher_id', 'subject_id', 'starts_at', 'ends_at', 'decision']);
        if ($row === null) {
            return null;
        }
        $starts = CarbonImmutable::parse($row->starts_at);

        return (object) [
            'id' => $row->id,
            'status' => $row->status,
            'type' => $row->type,
            'teacher_id' => $row->teacher_id,
            'subject_id' => $row->subject_id,
            'starts_at' => $starts,
            'booked_min' => (int) $starts->diffInMinutes(CarbonImmutable::parse($row->ends_at)),
            'decision' => $row->decision === null ? null : json_decode($row->decision, true),
        ];
    }

    /** @return list<string> lessons over in [from, to) */
    public function finalBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('sessions')->whereIn('status', self::FINAL)->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->orderBy('starts_at')->pluck('id')->all();
    }

    /** Lessons in [from, to) still waiting for an outcome (scheduled, under way, or with a manager). */
    public function pendingBetween(CarbonImmutable $from, CarbonImmutable $to, ?string $teacherId = null): int
    {
        return DB::table('sessions')->whereIn('status', ['scheduled', 'in_progress', 'needs_review'])->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))->count();
    }
}
