<?php

namespace Modules\Quran\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Authorization\LearningAccess;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Quran\Actions\Plans\AdjustQuranPlan;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Where a learner is on their Quran plan: for the student and their
 * guardians (each Quran course's plan, its next portion and the last
 * lessons' grades, never the teacher's notes; the kids' look without
 * counts), and for the course's teacher (with notes, and their own pace).
 */
final class QuranPlanController
{
    /** The last lessons' results shown with a plan. */
    public const RECENT = 10;

    public function __construct(
        private readonly Plans $plans,
        private readonly ProgramPresenter $present,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /**
     * A learner's Quran plans.
     *
     * Each Quran course with a plan: the program, where the learner is and how much is behind them, the next
     * portion (new, near and far review), whether it is frozen (the course paused or over), and the last lessons'
     * grades (mistakes by kind, except in the kids' look).
     */
    public function family(Request $request, FamilyView $family, QuranArea $area, CatalogApi $catalog): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $viewer = (string) $request->user()?->getAuthIdentifier();
        $asked = $request->query('learner_id', $request->input('learner_id'));
        $learner = $family->learner($viewer, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;
        $counts = $family->stage($viewer) !== LearnerStage::Kids;
        $subjects = $catalog->subjectNames();

        $courses = [];
        foreach ($this->enrollments->forStudent($learner) as $course) {
            $plan = $area->isQuranSubject($course->subjectId) ? $this->plans->forEnrollment($course->id) : null;
            if ($plan === null) {
                continue;
            }
            $courses[] = [
                'enrollment_id' => $course->id,
                'subject' => $subjects[$course->subjectId] ?? '',
                'plan' => $this->present->plan($plan, $this->plans->portion($plan), $this->plans->frozen($plan)),
                'recent' => $this->recent($plan, $learner)->map(fn (QuranLessonResult $r) => $this->present->result($r, family: true, counts: $counts))->all(),
            ];
        }

        return response()->json(['data' => ['learner_id' => $learner, 'courses' => $courses]]);
    }

    /**
     * A student's Quran plan.
     *
     * For the course's teacher: the plan (null when the course has none yet), its next portion, and the last
     * lessons' results with the teacher's notes.
     */
    public function teacher(Request $request, string $enrollment, LearningAccess $access): JsonResponse
    {
        $course = $this->course($request, $enrollment, $access);
        $plan = $this->plans->forEnrollment($course->id);

        return response()->json(['data' => [
            'enrollment_id' => $course->id,
            'plan' => $plan === null ? null : $this->present->plan($plan, $this->plans->portion($plan), $this->plans->frozen($plan)),
            'recent' => $plan === null ? [] : $this->recent($plan, $course->studentId)->map(fn (QuranLessonResult $r) => $this->present->result($r))->all(),
            'grades' => $this->present->grades(),
            'mistake_types' => $this->present->mistakeTypes(),
        ]]);
    }

    /**
     * Change a student's pace.
     *
     * The new amount a lesson (in the program's unit), the near review (portions) and the far review (pages); null
     * goes back to the program's. A group's plan is changed by the office.
     */
    public function pace(Request $request, string $enrollment, LearningAccess $access, AdjustQuranPlan $adjust): JsonResponse
    {
        $request->validate([
            'new_amount' => ['nullable', 'numeric'],
            'near_portions' => ['nullable', 'integer'],
            'far_pages' => ['nullable', 'integer'],
        ]);
        $course = $this->course($request, $enrollment, $access);
        $plan = $this->plans->forEnrollment($course->id);
        if ($plan === null || $plan->isGroup()) {
            throw new NotFoundHttpException;
        }
        $plan = $adjust->handle($plan, $request->only(['new_amount', 'near_portions', 'far_pages']), (string) $request->user()->getAuthIdentifier());

        return response()->json(['data' => ['enrollment_id' => $course->id, 'plan' => $this->present->plan($plan, $this->plans->portion($plan), $this->plans->frozen($plan))]]);
    }

    private function recent(QuranPlan $plan, string $studentId)
    {
        return QuranLessonResult::query()->where('plan_id', $plan->id)->where('student_id', $studentId)
            ->orderByDesc('recorded_at')->limit(self::RECENT)->get();
    }

    private function course(Request $request, string $enrollment, LearningAccess $access): EnrollmentData
    {
        $course = $this->enrollments->find($enrollment) ?? throw new NotFoundHttpException;
        abort_unless($access->seesTeacherMaterials($request->user(), $course), 403);

        return $course;
    }
}
