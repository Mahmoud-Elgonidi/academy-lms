<?php

namespace Modules\Learning\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Http\V1\Resources\LearningPresenter;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student's and the guardian's view (mobile app and web): each child's
 * enrollments with the roadmap, the handed-in reports (never the internal
 * notes), and homework to hand in.
 */
final class LearnerController
{
    public function __construct(
        private readonly LearningAccess $access,
        private readonly EnrollmentApi $enrollments,
        private readonly LearningPresenter $present,
    ) {}

    public function students(Request $request, RoadmapQuery $roadmap): JsonResponse
    {
        $ids = $this->access->learnersOf($request->user());
        $users = DB::table('users')->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');

        $data = $users->map(function (object $u) use ($roadmap, $subjects) {
            $enrollments = array_values(array_filter($this->enrollments->forStudent($u->id), fn (EnrollmentData $e) => $e->status->value !== 'ended'));

            return [
                'id' => $u->id,
                'name' => $u->name,
                'enrollments' => array_map(function (EnrollmentData $e) use ($roadmap, $subjects) {
                    $map = $roadmap->for($e->id);

                    return [
                        'id' => $e->id,
                        'subject' => $subjects[$e->subjectId] ?? null,
                        'teacher' => DB::table('users')->where('id', $e->teacherId)->value('name'),
                        'program' => $map['program']['name'] ?? null,
                        'percent' => $map['percent'] ?? null,
                        'current' => $map['current'] ?? null,
                        'eta_weeks' => $map['eta_weeks'] ?? null,
                        'homework_open' => Homework::query()->where('enrollment_id', $e->id)->whereIn('status', ['assigned', 'missed'])->count(),
                    ];
                }, $enrollments),
            ];
        })->filter(fn (array $s) => $s['enrollments'] !== [])->values();

        return response()->json(['data' => $data->all()]);
    }

    public function roadmap(Request $request, string $enrollment, RoadmapQuery $roadmap): JsonResponse
    {
        $this->enrollment($request, $enrollment);

        return response()->json(['data' => $roadmap->for($enrollment)]);
    }

    public function reports(Request $request, string $enrollment): JsonResponse
    {
        $this->enrollment($request, $enrollment);
        $reports = SessionReport::query()->with('items')->where('enrollment_id', $enrollment)->whereIn('status', ['submitted', 'reviewed'])
            ->orderByDesc('session_starts_at')->limit(50)->get();
        $titles = DB::table('curriculum_nodes')->whereIn('id', $reports->flatMap->items->pluck('node_id')->unique())
            ->get(['id', 'title_ar', 'title_en'])->mapWithKeys(fn ($n) => [$n->id => app()->getLocale() === 'ar' ? $n->title_ar : $n->title_en]);

        return response()->json(['data' => $reports->map(fn (SessionReport $r) => $this->present->report($r, false, $r->items->map(fn (ReportItem $i) => [
            'node_id' => $i->node_id, 'title' => $titles[$i->node_id] ?? null, 'outcome' => $i->outcome,
            'outcome_label' => __('learning::reports.outcomes.'.$i->outcome), 'score' => $i->score, 'note' => $i->note,
        ])->all()))->all()]);
    }

    public function homework(Request $request): JsonResponse
    {
        $students = $this->access->learnersOf($request->user());
        $only = $request->query('student_id');
        $homework = Homework::query()->with('submissions')
            ->whereIn('student_id', is_string($only) && in_array($only, $students, true) ? [$only] : $students)
            ->orderByRaw("case when status in ('assigned', 'missed') then 0 else 1 end")->orderByDesc('due_at')
            ->limit(100)->get();

        return response()->json(['data' => $homework->map(fn (Homework $h) => $this->present->homework($h))->all()]);
    }

    public function handIn(Request $request, string $homework, SubmitHomework $submit): JsonResponse
    {
        $homework = Homework::query()->findOrFail($homework);
        abort_unless($this->access->canHandIn($request->user(), $homework), 403);
        $submit->handle($homework, $request->user()->getAuthIdentifier(), $request->input('body'), $request->file('file'));

        return response()->json(['data' => $this->present->homework($homework->refresh()->load('submissions'))], 201);
    }

    public function file(Request $request, string $submission): StreamedResponse
    {
        $submission = HomeworkSubmission::query()->findOrFail($submission);
        $homework = Homework::query()->findOrFail($submission->homework_id);
        abort_unless($submission->file_path !== null && $this->access->canSeeHomework($request->user(), $homework), 404);

        return Storage::disk(SubmitHomework::DISK)->download($submission->file_path, $submission->file_name, ['Content-Type' => $submission->mime]);
    }

    private function enrollment(Request $request, string $id): EnrollmentData
    {
        $enrollment = $this->enrollments->find($id);
        abort_unless($enrollment !== null && $this->access->canSeeEnrollment($request->user(), $enrollment), 404);

        return $enrollment;
    }
}
