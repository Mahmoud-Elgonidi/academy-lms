<?php

namespace Modules\Learning\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Models\SessionReport;

/**
 * The teacher's report on a group lesson (docs/architecture/02, "الحصص
 * الجماعية"): the class's shared part written once (what was covered, the
 * subject's questions, the homework, which goes to every student in it) and
 * a short row for each student (their scores and a note for them, or the
 * absence note of one who did not come). Each student keeps a report of
 * their own, handed in through SubmitSessionReport, all of them together or
 * none.
 */
final class SubmitGroupReport
{
    /** A student's own part of the report. */
    public const ROW = ['engagement', 'overall', 'notes_for_student', 'absence_note'];

    /** The class's shared part, written once. */
    public const SHARED = ['items', 'answers', 'homework', 'notes_internal'];

    public function __construct(private readonly SubmitSessionReport $submit) {}

    /**
     * @param  array<string, mixed>  $shared  items, answers, homework, notes_internal
     * @param  list<array{student_id: string, engagement?: ?int, overall?: ?int, notes_for_student?: ?string, absence_note?: ?string}>  $students
     * @return list<SessionReport> every student's report, the one asked for first
     */
    public function handle(SessionReport $report, string $actorId, array $shared, array $students): array
    {
        $reports = $this->reportsOf($report);
        $rows = collect($students)->filter(fn ($r) => is_array($r) && is_string($r['student_id'] ?? null))->keyBy('student_id');
        if ($reports->keys()->diff($rows->keys())->isNotEmpty() || $rows->keys()->diff($reports->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['students' => __('learning::group_reports.errors.students')]);
        }
        $shared = array_intersect_key($shared, array_flip(self::SHARED));

        return DB::transaction(function () use ($reports, $rows, $shared, $actorId, $report) {
            $done = [];
            foreach ($reports as $studentId => $one) {
                if ($one->status === 'reviewed') {
                    $done[$studentId] = $one;     // a supervisor has it: it stays as it was

                    continue;
                }
                $row = array_intersect_key($rows[$studentId], array_flip(self::ROW));
                $data = $one->kind === 'lesson'
                    ? $row + $shared
                    : ['absence_note' => $row['absence_note'] ?? null, 'notes_internal' => $shared['notes_internal'] ?? null, 'homework' => $shared['homework'] ?? null];
                try {
                    $done[$studentId] = $this->submit->handle($one, $actorId, $data);
                } catch (ValidationException $e) {
                    // Which student's row: a student's own fields are named by their place in the rows.
                    $at = array_search($studentId, $rows->keys()->all(), true);
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $key) => [
                        (in_array($key, self::ROW, true) ? "students.{$at}." : '').$key => $m,
                    ])->all());
                }
            }

            return [$done[$report->student_id], ...array_values(array_diff_key($done, [$report->student_id => true]))];
        });
    }

    /** @return Collection<string, SessionReport> the lesson's reports by student, its teacher's */
    public function reportsOf(SessionReport $report): Collection
    {
        return SessionReport::query()->where('session_id', $report->session_id)->where('teacher_id', $report->teacher_id)
            ->orderBy('student_id')->get()->keyBy('student_id');
    }

    public static function isGroup(SessionReport $report): bool
    {
        return DB::table('sessions')->where('id', $report->session_id)->whereNotNull('group_id')->exists();
    }
}
