<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Domain\MasteryRule;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateAnswers;

/**
 * The teacher hands in the report (docs/architecture/01, "التقارير والواجبات";
 * 02, section 6): for a lesson, what was covered and how it went, scores and
 * notes, and optionally homework; for an absence, a short note. It can be
 * corrected until a supervisor has reviewed it. The progress projector runs
 * in the same transaction, so the roadmap is never behind the report. A
 * lesson report also answers its subject's template (the version it was
 * first handed in with, else the one now) and keeps which version that was.
 */
final class SubmitSessionReport
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CurriculumApi $curriculum,
        private readonly ProjectProgress $project,
        private readonly AssignHomework $assign,
    ) {}

    /**
     * @param  array{engagement?: ?int, overall?: ?int, notes_for_student?: ?string, notes_internal?: ?string, absence_note?: ?string,
     *     items?: list<array{node_id: string, outcome: string, score?: ?int, note?: ?string}>, answers?: array<string, mixed>,
     *     homework?: ?array{title?: ?string, instructions?: ?string, accepts?: ?string, due_days?: ?int, practice?: ?bool}}  $data
     */
    public function handle(SessionReport $report, string $actorId, array $data): SessionReport
    {
        if ($report->status === 'reviewed') {
            throw ValidationException::withMessages(['report' => __('learning::reports.errors.locked')]);
        }
        $tree = $this->treeFor($report);
        $data = $this->validate($report, $tree, $data);
        $template = ReportTemplate::forReport($report);
        $answers = $template === null ? [] : TemplateAnswers::check($template, is_array($data['answers'] ?? null) ? $data['answers'] : []);

        DB::transaction(function () use ($report, $actorId, $data, $template, $answers) {
            $now = CarbonImmutable::now();
            $first = $report->submitted_at === null;
            $lesson = $report->kind === 'lesson';
            $report->fill([
                'status' => 'submitted',
                'submitted_at' => $first ? $now : $report->submitted_at,
                'late' => $first ? $now->greaterThan($report->due_at) : $report->late,
                'engagement' => $lesson ? (int) $data['engagement'] : null,
                'overall' => $lesson ? (int) $data['overall'] : null,
                'notes_for_student' => $lesson ? (($data['notes_for_student'] ?? null) ?: null) : null,
                'notes_internal' => ($data['notes_internal'] ?? null) ?: null,
                'absence_note' => $lesson ? null : $data['absence_note'],
                'template_id' => $template?->id,
                'answers' => $answers,
            ])->save();

            $report->items()->delete();
            foreach ($lesson ? ($data['items'] ?? []) : [] as $item) {
                $report->items()->create([
                    'node_id' => $item['node_id'],
                    'outcome' => $item['outcome'],
                    'score' => isset($item['score']) && $item['score'] !== '' ? (int) $item['score'] : null,
                    'note' => ($item['note'] ?? null) ?: null,
                ]);
            }

            $this->homework($report, $actorId, $data['homework'] ?? null, $now);

            $mastered = $report->enrollment_id !== null && $lesson ? $this->project->handle($report->enrollment_id) : [];

            DB::afterCommit(function () use ($report, $first, $mastered) {
                if ($first) {
                    event(new SessionReportSubmitted($report->academy_id, $report->id, $report->session_id, $report->enrollment_id, $report->student_id, $report->teacher_id, $report->kind, $report->late));
                }
                foreach ($mastered as $nodeId) {
                    event(new NodeMastered($report->academy_id, (string) $report->enrollment_id, $report->student_id, $nodeId));
                }
            });
        });

        return $report->refresh()->load('items', 'homework');
    }

    private function treeFor(SessionReport $report): ?CurriculumTree
    {
        $versionId = $report->enrollment_id === null ? null : $this->enrollments->find($report->enrollment_id)?->curriculumVersionId;

        return $versionId === null ? null : $this->curriculum->tree($versionId);
    }

    /** @return array<string, mixed> */
    private function validate(SessionReport $report, ?CurriculumTree $tree, array $data): array
    {
        $lessonIds = $tree === null ? [] : array_map(fn ($n) => $n->id, $tree->lessons());
        $rules = $report->kind === 'absence'
            ? ['absence_note' => ['required', 'string', 'max:1000'], 'notes_internal' => ['nullable', 'string', 'max:3000']]
            : [
                'engagement' => ['required', 'integer', 'between:1,5'],
                'overall' => ['required', 'integer', 'between:1,5'],
                'notes_for_student' => ['nullable', 'string', 'max:3000'],
                'notes_internal' => ['nullable', 'string', 'max:3000'],
                'items' => [$tree === null ? 'nullable' : 'required', 'array', 'max:10'],
                'items.*.node_id' => ['required', 'distinct', Rule::in($lessonIds)],
                'items.*.outcome' => ['required', Rule::in(MasteryRule::OUTCOMES)],
                'items.*.score' => ['nullable', 'integer', 'between:1,5'],
                'items.*.note' => ['nullable', 'string', 'max:500'],
            ];
        $rules += [
            'homework' => ['nullable', 'array'],
            'homework.title' => ['nullable', 'string', 'max:200'],
            'homework.instructions' => ['nullable', 'string', 'max:3000'],
            'homework.accepts' => ['nullable', Rule::in(Homework::ACCEPTS)],
            'homework.due_days' => ['nullable', 'integer', 'between:1,30'],
            'homework.practice' => ['nullable', 'boolean'],
        ];

        return Validator::make($data, $rules, [], trans('learning::reports.attributes'))->validate() + $data;
    }

    /**
     * Homework from this report: set it, change it while nothing was handed
     * in, or take it back when the title was cleared. With practice (or when
     * practice is all it asks for), it points at the report's lessons: the
     * learner practises their items in the games.
     *
     * @param  array{title?: ?string, instructions?: ?string, accepts?: ?string, due_days?: ?int, practice?: ?bool}|null  $input
     */
    private function homework(SessionReport $report, string $actorId, ?array $input, CarbonImmutable $now): void
    {
        $existing = $report->homework()->first();
        $title = trim((string) ($input['title'] ?? ''));
        $untouched = $existing !== null && $existing->status === 'assigned' && ! $existing->submissions()->exists();

        if ($title === '') {
            if ($untouched) {
                $existing->delete();
            }

            return;
        }

        $accepts = in_array($input['accepts'] ?? null, Homework::ACCEPTS, true) ? $input['accepts'] : 'any';
        $lessons = $report->items()->pluck('node_id')->all();
        $practice = ($accepts === 'practice' || filter_var($input['practice'] ?? false, FILTER_VALIDATE_BOOLEAN)) && $lessons !== [];
        $fields = [
            'title' => $title,
            'instructions' => ($input['instructions'] ?? null) ?: null,
            'accepts' => $accepts === 'practice' && ! $practice ? 'any' : $accepts,
            'due_at' => ($report->submitted_at ?? $now)->addDays((int) ($input['due_days'] ?? LearningSettings::current()->homework_days)),
            'practice_nodes' => $practice ? array_values($lessons) : null,
        ];
        if ($existing === null) {
            $this->assign->handle($fields + [
                'teacher_id' => $report->teacher_id,
                'student_id' => $report->student_id,
                'enrollment_id' => $report->enrollment_id,
                'session_id' => $report->session_id,
                'report_id' => $report->id,
            ]);
        } elseif ($untouched) {
            $existing->fill($fields)->save();
        }
    }
}
