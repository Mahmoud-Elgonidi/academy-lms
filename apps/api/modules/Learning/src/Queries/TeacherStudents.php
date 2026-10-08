<?php

namespace Modules\Learning\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Contracts\DTOs\MaterialLine;
use Modules\Learning\Materials\MaterialLinks;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;

/** A teacher's students: each running course with where the student is on the roadmap (and that lesson's materials), the last report, and homework to mark. */
final class TeacherStudents
{
    public function __construct(private readonly RoadmapQuery $roadmap, private readonly MaterialLinks $materials) {}

    /** @return list<array{enrollment_id: string, student_id: string, student: string, subject: string, paused: bool, percent: ?int, here: ?string, materials: list<array<string, mixed>>, last_report_at: ?CarbonImmutable, to_mark: int}> */
    public function of(string $teacherId): array
    {
        $rows = DB::table('enrollments as e')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.teacher_id', $teacherId)->whereIn('e.status', ['active', 'pending_payment', 'paused'])
            ->orderBy('u.name')
            ->get(['e.id', 'e.status', 'e.student_id', 'u.name as student', 's.name_ar', 's.name_en']);
        $ids = $rows->pluck('id')->all();
        $lastReport = SessionReport::query()->whereIn('enrollment_id', $ids)->whereIn('status', ['submitted', 'reviewed'])
            ->selectRaw('enrollment_id, max(session_starts_at) as at')->groupBy('enrollment_id')->pluck('at', 'enrollment_id');
        $toMark = Homework::query()->whereIn('enrollment_id', $ids)->where('status', 'submitted')
            ->selectRaw('enrollment_id, count(*) as n')->groupBy('enrollment_id')->pluck('n', 'enrollment_id');

        return $rows->map(function (object $r) use ($lastReport, $toMark) {
            $map = $this->roadmap->for($r->id);

            return [
                'enrollment_id' => $r->id,
                'student_id' => $r->student_id,
                'student' => $r->student,
                'subject' => app()->getLocale() === 'ar' ? $r->name_ar : $r->name_en,
                'paused' => $r->status !== 'active',
                'percent' => $map['percent'] ?? null,
                'here' => $map ? ($map['finished'] ? __('learning::progress.finished') : ($map['current']['title'] ?? null)) : null,
                // The lesson now's files and links, the teachers' too (a file's url is signed for twelve hours).
                'materials' => ($id = $map['current']['id'] ?? null) === null ? [] : array_map(fn (MaterialLine $m) => $m->toArray(), $this->materials->forLessons([$id], true)[$id] ?? []),
                'last_report_at' => isset($lastReport[$r->id]) ? CarbonImmutable::parse($lastReport[$r->id])->utc() : null,
                'to_mark' => (int) ($toMark[$r->id] ?? 0),
            ];
        })->all();
    }
}
