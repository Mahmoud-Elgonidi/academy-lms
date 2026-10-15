<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\Hifz\ClearHifzPages;
use Modules\Learning\Actions\Hifz\MarkMemorized;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Http\V1\Resources\HifzPresenter;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A student's hifz map for their teacher: where each page stands, the last
 * two weeks' reviews, and saying which pages the student already knows by
 * heart ("part 30, well") or taking pages off by mistake.
 */
final class TeacherHifzController
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly LearningAccess $access,
        private readonly HifzPresenter $present,
        private readonly HifzPages $pages,
    ) {}

    /**
     * A student's hifz map.
     *
     * Every page by part with its strength, the counts, the last two weeks' reviews (grade and where each came from),
     * and the parts and surahs with their pages, to say what the student already knows.
     */
    public function show(Request $request, string $enrollment, CurriculumApi $curriculum, HifzMap $map, LocalTimePresenter $time): JsonResponse
    {
        $course = $this->course($request, $enrollment);
        $now = CarbonImmutable::now();

        return response()->json(['data' => [
            'enrollment_id' => $course->id,
            'student' => ['id' => $course->studentId, 'name' => (string) DB::table('users')->where('id', $course->studentId)->value('name')],
            ...$this->present->map($course->studentId, $now),
            'recent' => collect($map->recent($course->studentId, $now, 100))->map(fn (array $r) => [
                'page' => (int) $r['page'],
                'grade' => (int) $r['grade'],
                'grade_label' => __('learning::hifz.grades.'.$r['grade']),
                /** teacher, report, check (homework), wird (a page recited for the daily review) or self (their own grade) */
                'source' => (string) $r['source'],
                'source_label' => __('learning::hifz.sources.'.$r['source']),
                'at' => $time->present($r['reviewed_at']),
            ])->all(),
            /** What a teacher may mark: whole parts, whole surahs, or pages. */
            'surahs' => array_map(fn (array $s) => $s + ['pages' => $curriculum->quranPagesOf(TextSource::quran($s['number'], 1, $s['verses']))], $curriculum->surahs()),
            'marks' => trans('learning::hifz.marks'),
        ]]);
    }

    /**
     * Say what the student already knows by heart.
     *
     * Whole parts (juz), surahs or pages, and how well: strong, good or weak. Each page starts again as memorized,
     * due for review as that says.
     */
    public function mark(Request $request, string $enrollment, MarkMemorized $mark): JsonResponse
    {
        $course = $this->course($request, $enrollment);
        $request->validate($this->choice() + ['strength' => ['required', Rule::in(array_keys(Scheduler::MARKED))]], [], trans('learning::hifz.attributes'));
        $count = $mark->handle($course->studentId, $this->chosen($request), (string) $request->input('strength'));

        return response()->json(['data' => ['marked' => $count] + $this->present->map($course->studentId, CarbonImmutable::now())]);
    }

    /** Take pages off the student's map (marked by mistake): they are new again. */
    public function clear(Request $request, string $enrollment, ClearHifzPages $clear): JsonResponse
    {
        $course = $this->course($request, $enrollment);
        $request->validate($this->choice(), [], trans('learning::hifz.attributes'));
        $count = $clear->handle($course->studentId, $this->chosen($request));

        return response()->json(['data' => ['cleared' => $count] + $this->present->map($course->studentId, CarbonImmutable::now())]);
    }

    /** @return array<string, list<mixed>> */
    private function choice(): array
    {
        return [
            'juz' => ['nullable', 'array', 'max:30'], 'juz.*' => ['integer'],
            'surahs' => ['nullable', 'array', 'max:114'], 'surahs.*' => ['integer'],
            'pages' => ['nullable', 'array', 'max:604'], 'pages.*' => ['integer'],
        ];
    }

    /** @return list<int> */
    private function chosen(Request $request): array
    {
        $pages = $this->pages->chosen((array) $request->input('juz', []), (array) $request->input('surahs', []), (array) $request->input('pages', []));

        return $pages !== [] ? $pages : throw ValidationException::withMessages(['pages' => __('validation.required', ['attribute' => __('learning::hifz.attributes.pages')])]);
    }

    private function course(Request $request, string $enrollment): EnrollmentData
    {
        $course = $this->enrollments->find($enrollment) ?? throw new NotFoundHttpException;
        abort_unless($this->access->seesTeacherMaterials($request->user(), $course), 403);

        return $course;
    }
}
