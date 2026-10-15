<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Learning\Actions\Assist\DraftReportFromVoice;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Http\V1\Resources\LearningPresenter;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\Learning\Queries\TeacherStudents;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The teacher's reports and the homework to mark (mobile app). Every change goes through the same Actions as the screens. */
final class TeacherReportsController
{
    /** A voice note for a draft: two minutes, as browsers and phones record them. */
    public const VOICE_MAX_KB = 6 * 1024;

    public function __construct(
        private readonly LearningAccess $access,
        private readonly LearningPresenter $present,
        private readonly ReportFormQuery $form,
    ) {}

    /**
     * My students.
     *
     * Each running course I teach: the student, where they are on the roadmap (GET /learning/enrollments/{id}/roadmap
     * for all of it), when I last reported, and homework waiting for my mark.
     */
    public function students(Request $request, TeacherStudents $students, LocalTimePresenter $time): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (array $r) => ['last_report_at' => $time->present($r['last_report_at'])] + $r,
            $students->of((string) $request->user()->getAuthIdentifier()),
        )]);
    }

    public function index(Request $request): JsonResponse
    {
        $teacherId = $request->user()->getAuthIdentifier();
        $reports = SessionReport::query()->where('teacher_id', $teacherId)
            ->where(fn ($q) => $q->whereIn('status', SessionReport::OPEN)->orWhere('submitted_at', '>=', CarbonImmutable::now()->subDays(14)))
            ->orderByRaw("case when status in ('due', 'overdue') then 0 else 1 end")->orderBy('due_at')
            ->limit(100)->get();

        return response()->json(['data' => $reports->map(fn (SessionReport $r) => $this->present->report($r, true))->all()]);
    }

    /**
     * A report to write or correct.
     *
     * The report with today's plan, the lessons to choose from, the homework set from it, the roadmap, and the
     * subject's own questions (`template`, null when it has none); send their answers as `answers` by key.
     */
    public function show(Request $request, string $report, RoadmapQuery $roadmap, AcademyAi $ai): JsonResponse
    {
        $report = $this->mine($request, $report);

        return response()->json(['data' => $this->present->report($report, true, $this->form->items($report)) + [
            'template' => $this->present->template(ReportTemplate::forReport($report)),
            'lesson_options' => collect($this->form->lessonOptions($report))->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all(),
            'homework' => ($h = $report->homework()->with('submissions')->first()) ? $this->present->homework($h) : null,
            'roadmap' => $report->enrollment_id ? $roadmap->for($report->enrollment_id, RoadmapQuery::MATERIALS_TEACHERS) : null,
            // Helpers the academy turned on for this report: a draft from a voice note (POST teacher/reports/{report}/draft).
            'assist' => ['voice_draft' => $report->status !== 'reviewed' && $ai->on(AiFeature::ReportDraft)],
        ]]);
    }

    /**
     * Draft the report from a voice note.
     *
     * The teacher's own note (`audio`, up to two minutes) is heard and deleted at once; what was said fills the
     * fields the open form (`current`, as PUT takes it, JSON) leaves empty. Nothing is saved: `report` is the form
     * with the draft in it, `filled` the fields it filled (lessons as `items.{node_id}`, questions as
     * `answers.{key}`), `unsure` those to check first, `left` what the report still needs, `heard` the words heard.
     */
    public function draft(Request $request, string $report, DraftReportFromVoice $draft): JsonResponse
    {
        $report = $this->mine($request, $report);
        $request->validate([
            'audio' => ['required', 'file', 'max:'.self::VOICE_MAX_KB, 'mimetypes:'.implode(',', SubmitHomework::AUDIO), new CleanFile],
            'current' => ['nullable', 'json', 'max:60000'],
        ]);
        $current = json_decode((string) $request->input('current', '{}'), true);

        return response()->json(['data' => $draft->handle(
            $report,
            (string) $request->user()->getAuthIdentifier(),
            AudioClip::fromUpload($request->file('audio')),
            is_array($current) ? $current : [],
        )]);
    }

    /**
     * Hand in or correct a report.
     *
     * Answers to the subject's questions go in `answers`, by the field's key (stars 1-5, a number, a choice's key,
     * true or false, a text).
     */
    public function submit(Request $request, string $report, SubmitSessionReport $submit): JsonResponse
    {
        $report = $submit->handle($this->mine($request, $report), $request->user()->getAuthIdentifier(), $request->only([
            'engagement', 'overall', 'notes_for_student', 'notes_internal', 'absence_note', 'items', 'homework', 'answers',
        ]));

        return response()->json(['data' => $this->present->report($report, true, $this->form->items($report))]);
    }

    public function homework(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), Homework::STATUSES, true) ? $request->query('status') : 'submitted';
        $homework = Homework::query()->with('submissions')->where('teacher_id', $request->user()->getAuthIdentifier())
            ->where('status', $status)->orderBy('due_at')->limit(100)->get();

        return response()->json(['data' => $homework->map(fn (Homework $h) => $this->present->homework($h))->all()]);
    }

    public function mark(Request $request, string $homework, ReviewHomework $review): JsonResponse
    {
        $homework = Homework::query()->findOrFail($homework);
        abort_unless($this->access->marksHomework($request->user(), $homework), 403);
        $homework = $review->handle($homework, $request->user()->getAuthIdentifier(), $request->filled('grade') ? (int) $request->input('grade') : null, $request->input('feedback'));

        return response()->json(['data' => $this->present->homework($homework->load('submissions'))]);
    }

    private function mine(Request $request, string $id): SessionReport
    {
        $report = SessionReport::query()->findOrFail($id);
        abort_unless($this->access->writesReport($request->user(), $report), 403);

        return $report;
    }
}
