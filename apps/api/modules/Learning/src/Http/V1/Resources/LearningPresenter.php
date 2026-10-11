<?php

namespace Modules\Learning\Http\V1\Resources;

use Illuminate\Support\Facades\DB;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateAnswers;
use Modules\Learning\Templates\TemplateField;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** Reports and homework as the apps read them; times always through LocalTimePresenter. */
final class LearningPresenter
{
    /** @var array<string, string> */
    private array $names = [];

    /** @var array<string, ?ReportTemplate> */
    private array $templates = [];

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
            // The academy's own questions for this subject, answered (only those the family reads, for the family).
            'answers' => TemplateAnswers::present($this->templateOf($r), $r->answers ?? [], $internal),
        ];
        if ($internal) {
            $out += ['notes_internal' => $r->notes_internal, 'review_note' => $r->review_note, 'reviewed_at' => $this->time->present($r->reviewed_at)];
        }

        return $out;
    }

    /**
     * The questions a report form asks after the usual ones; null when the subject has none.
     *
     * @return ?array<string, mixed>
     */
    public function template(?ReportTemplate $t): ?array
    {
        if ($t === null || $t->fields === []) {
            return null;
        }

        return [
            'id' => $t->id,
            'version' => $t->version,
            'fields' => array_map(fn (TemplateField $f) => [
                'key' => $f->key,
                'label' => $f->label(),
                'type' => $f->type,              // rating (1-5), number, choice, yes_no, text
                'required' => $f->required,
                'family' => $f->family,          // false: the answer stays with the academy
                'options' => array_map(fn (string $k, string $label) => ['key' => $k, 'label' => $label], array_keys($f->choices()), $f->choices()),
                'min' => $f->min,
                'max' => $f->type === 'rating' ? 5 : $f->max,
                'unit' => $f->unit(),
            ], $t->fieldList()),
        ];
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
            // Practice in the games on the report's lessons (null: none): how the best round went, or not yet.
            'practice' => $h->hasPractice() ? [
                'done' => $h->practice_result !== null,
                'correct' => $h->practice_result['correct'] ?? null,
                'total' => $h->practice_result['total'] ?? null,
                'stars' => $h->practice_result['stars'] ?? null,
                'game' => $h->practice_result['game'] ?? null,
                'label' => $h->practice_result === null ? __('learning::homework.practice.waiting')
                    : __('learning::homework.practice.result', ['correct' => $h->practice_result['correct'], 'total' => $h->practice_result['total'], 'stars' => $h->practice_result['stars'], 'game' => $h->practice_result['game']]),
            ] : null,
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

    private function templateOf(SessionReport $r): ?ReportTemplate
    {
        return $r->template_id === null ? null : ($this->templates[$r->template_id] ??= ReportTemplate::query()->find($r->template_id));
    }

    private function name(string $userId): string
    {
        return $this->names[$userId] ??= (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
