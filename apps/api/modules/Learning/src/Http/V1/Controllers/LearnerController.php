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
use Modules\Learning\Contracts\DTOs\MaterialLine;
use Modules\Learning\Http\V1\Resources\LearningPresenter;
use Modules\Learning\Materials\MaterialLinks;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\JourneyQuery;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\LocalTimePresenter;
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

    /**
     * The learners and their courses.
     *
     * Each running course: the program, how far, the lesson now and its materials (files and links meant for
     * everyone; a file's url is signed for twelve hours), the weeks left and open homework.
     */
    public function students(Request $request, RoadmapQuery $roadmap, MaterialLinks $materials): JsonResponse
    {
        $ids = $this->access->learnersOf($request->user());
        $users = DB::table('users')->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');

        $data = $users->map(function (object $u) use ($roadmap, $subjects, $materials) {
            $enrollments = array_values(array_filter($this->enrollments->forStudent($u->id), fn (EnrollmentData $e) => $e->status->value !== 'ended'));

            return [
                'id' => $u->id,
                'name' => $u->name,
                'enrollments' => array_map(function (EnrollmentData $e) use ($roadmap, $subjects, $materials) {
                    $map = $roadmap->for($e->id);
                    $here = $map['current']['id'] ?? null;

                    return [
                        'id' => $e->id,
                        'subject' => $subjects[$e->subjectId] ?? null,
                        'teacher' => DB::table('users')->where('id', $e->teacherId)->value('name'),
                        'program' => $map['program']['name'] ?? null,
                        'percent' => $map['percent'] ?? null,
                        'current' => $map['current'] ?? null,
                        'current_materials' => $here === null ? [] : array_map(fn (MaterialLine $m) => $m->toArray(), $materials->forLessons([$here], false)[$here] ?? []),
                        'eta_weeks' => $map['eta_weeks'] ?? null,
                        'homework_open' => Homework::query()->where('enrollment_id', $e->id)->whereIn('status', ['assigned', 'missed'])->count(),
                    ];
                }, $enrollments),
            ];
        })->filter(fn (array $s) => $s['enrollments'] !== [])->values();

        return response()->json(['data' => $data->all()]);
    }

    /**
     * A learner's journey.
     *
     * What the student's own home shows in each stage's look: stars (lessons mastered), points and rank, the lesson
     * streak, badges, and per course its levels (worlds) with each lesson's state, where they are, what is next and
     * the expected finish. learner_id defaults to the student themself, or a guardian's first child. Kept for
     * earlier app versions: the journey is now learning/journey/map.
     *
     * @deprecated
     */
    public function journey(Request $request, JourneyQuery $journey, LocalTimePresenter $time, FamilyView $family): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $asked = $request->query('learner_id');
        $learner = $family->learner((string) $request->user()?->getAuthIdentifier(), is_string($asked) && $asked !== '' ? $asked : null)?->id;
        abort_if($learner === null, 404);

        $j = $journey->for($learner);
        $l = 'learning::journey.';

        return response()->json(['data' => [
            'learner_id' => $learner,
            'stars' => $j['stars'],
            'attended' => $j['attended'],
            'on_time' => $j['on_time'],
            'streak' => $j['streak'],
            'points' => $j['points'],
            'rank' => $j['rank'] + [
                'label' => __($l.'ranks.'.$j['rank']['key']),
                'next_label' => $j['rank']['next_key'] ? __($l.'ranks.'.$j['rank']['next_key']) : null,
            ],
            'badges' => array_map(fn (array $b) => $b + ['name' => __($l.'badges.'.$b['key'])[0], 'hint' => __($l.'badges.'.$b['key'])[1]], $j['badges']),
            'courses' => array_map(fn (array $c) => [
                'enrollment_id' => $c['id'],
                'subject' => $c['subject'],
                'teacher' => $c['teacher'],
                'program' => $c['map']['program']['name'] ?? null,
                'percent' => $c['map']['percent'] ?? null,
                'finished' => (bool) ($c['map']['finished'] ?? false),
                'lessons_per_week' => $c['map']['lessons_per_week'] ?? null,
                'finish_on' => $time->present($c['finish_on']),
                'stars' => $c['stars'],
                'streak' => $c['streak'],
                'points' => $c['points'],
                /** Each level: done, here or ahead, with its lessons (state: done, review, here, todo). */
                'worlds' => array_map(fn (array $w) => [
                    'index' => $w['index'],
                    'title' => $w['title'],
                    'percent' => $w['percent'],
                    'state' => $w['state'],
                    'done' => $w['done'],
                    'lessons' => array_map(fn (array $lesson) => ['id' => $lesson['id'] ?? null, 'title' => $lesson['title'] ?? null, 'state' => $lesson['state'] ?? null], $w['lessons']),
                ], $c['worlds']),
                'level' => $c['level'] === null ? null : ['index' => $c['level']['index'], 'title' => $c['level']['title'], 'count' => $c['level']['count'], 'weeks_left' => $c['level']['weeks_left']],
                'next' => array_map(fn (array $lesson) => ['id' => $lesson['id'] ?? null, 'title' => $lesson['title'] ?? null, 'state' => $lesson['state'] ?? null], $c['next']),
            ], $j['courses']),
        ]]);
    }

    /**
     * A course's roadmap.
     *
     * Levels, units and lessons with where the student is, and each lesson's materials (files and links; a file's
     * url is signed for twelve hours and opens without signing in).
     */
    public function roadmap(Request $request, string $enrollment, RoadmapQuery $roadmap): JsonResponse
    {
        $materials = $this->access->seesTeacherMaterials($request->user(), $this->enrollment($request, $enrollment))
            ? RoadmapQuery::MATERIALS_TEACHERS : RoadmapQuery::MATERIALS_FAMILY;

        return response()->json(['data' => $roadmap->for($enrollment, $materials)]);
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
