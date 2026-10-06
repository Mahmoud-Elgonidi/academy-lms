<?php

namespace Modules\Learning\Http\V1\Resources;

use Illuminate\Support\Facades\DB;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\SessionReport;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** Reports and homework as the apps read them; times always through LocalTimePresenter. */
final class LearningPresenter
{
    /** @var array<string, string> */
    private array $names = [];

    public function __construct(private readonly LocalTimePresenter $time) {}

    /** @return array<string, mixed> */
    public function report(SessionReport $r, bool $internal, ?array $items = null): array
    {
        $out = [
            'id' => $r->id,
            'session_id' => $r->session_id,
            'enrollment_id' => $r->enrollment_id,
            'kind' => $r->kind,
            'status' => $r->status,
            'status_label' => __('learning::reports.statuses.'.$r->status),
            'student' => ['id' => $r->student_id, 'name' => $this->name($r->student_id)],
            'teacher' => ['id' => $r->teacher_id, 'name' => $this->name($r->teacher_id)],
            'lesson_at' => $this->time->present($r->session_starts_at),
            'due_at' => $this->time->present($r->due_at),
            'submitted_at' => $this->time->present($r->submitted_at),
            'late' => $r->late,
            'engagement' => $r->engagement,
            'overall' => $r->overall,
            'notes_for_student' => $r->notes_for_student,
            'absence_note' => $r->absence_note,
            'items' => $items ?? [],
        ];
        if ($internal) {
            $out += ['notes_internal' => $r->notes_internal, 'review_note' => $r->review_note, 'reviewed_at' => $this->time->present($r->reviewed_at)];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function homework(Homework $h): array
    {
        return [
            'id' => $h->id,
            'report_id' => $h->report_id,
            'enrollment_id' => $h->enrollment_id,
            'student' => ['id' => $h->student_id, 'name' => $this->name($h->student_id)],
            'teacher' => ['id' => $h->teacher_id, 'name' => $this->name($h->teacher_id)],
            'title' => $h->title,
            'instructions' => $h->instructions,
            'accepts' => $h->accepts,
            'status' => $h->status,
            'status_label' => __('learning::homework.statuses.'.$h->status),
            'due_at' => $this->time->present($h->due_at),
            'grade' => $h->grade,
            'feedback' => $h->feedback,
            'reviewed_at' => $this->time->present($h->reviewed_at),
            'submissions' => $h->submissions->map(fn (HomeworkSubmission $s) => [
                'id' => $s->id,
                'body' => $s->body,
                'file' => $s->file_path === null ? null : [
                    'name' => $s->file_name, 'mime' => $s->mime, 'size' => $s->size, 'is_audio' => $s->isAudio(),
                    'url' => route('learning.submissions.file', ['submission' => $s->id]),
                ],
                'submitted_at' => $this->time->present($s->submitted_at),
            ])->values()->all(),
        ];
    }

    private function name(string $userId): string
    {
        return $this->names[$userId] ??= (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
