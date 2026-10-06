<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Http\V1\Resources\LearningPresenter;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\Learning\Queries\RoadmapQuery;

/** The teacher's reports and the homework to mark (mobile app). Every change goes through the same Actions as the screens. */
final class TeacherReportsController
{
    public function __construct(
        private readonly LearningAccess $access,
        private readonly LearningPresenter $present,
        private readonly ReportFormQuery $form,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teacherId = $request->user()->getAuthIdentifier();
        $reports = SessionReport::query()->where('teacher_id', $teacherId)
            ->where(fn ($q) => $q->whereIn('status', SessionReport::OPEN)->orWhere('submitted_at', '>=', CarbonImmutable::now()->subDays(14)))
            ->orderByRaw("case when status in ('due', 'overdue') then 0 else 1 end")->orderBy('due_at')
            ->limit(100)->get();

        return response()->json(['data' => $reports->map(fn (SessionReport $r) => $this->present->report($r, true))->all()]);
    }

    public function show(Request $request, string $report, RoadmapQuery $roadmap): JsonResponse
    {
        $report = $this->mine($request, $report);

        return response()->json(['data' => $this->present->report($report, true, $this->form->items($report)) + [
            'lesson_options' => collect($this->form->lessonOptions($report))->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all(),
            'homework' => ($h = $report->homework()->with('submissions')->first()) ? $this->present->homework($h) : null,
            'roadmap' => $report->enrollment_id ? $roadmap->for($report->enrollment_id) : null,
        ]]);
    }

    public function submit(Request $request, string $report, SubmitSessionReport $submit): JsonResponse
    {
        $report = $submit->handle($this->mine($request, $report), $request->user()->getAuthIdentifier(), $request->only([
            'engagement', 'overall', 'notes_for_student', 'notes_internal', 'absence_note', 'items', 'homework',
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
