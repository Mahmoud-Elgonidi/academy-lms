<?php

namespace Modules\Quran\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Quran\Actions\Exams\RecordQuranExam;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\QuranExam;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Quran exams (docs/architecture/02, section 26): the examiner's own queue
 * and recording a result; a learner's exams for the student and their
 * guardians (no counts in the kids' look, never why one was cancelled).
 */
final class QuranExamController
{
    /** The last results shown in the examiner's queue. */
    public const RECENT = 10;

    public function __construct(private readonly ExamPresenter $present) {}

    /**
     * The examiner's exams.
     *
     * The exams booked with the person signed in as examiner (soonest first), with the academy's mistake types and
     * grades, and the last results they recorded.
     */
    public function examiner(Request $request, ProgramPresenter $program): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $booked = QuranExam::query()->with('program')->where('examiner_id', $me)->where('status', 'booked')->orderBy('booked_at')->get();
        $recent = QuranExam::query()->with('program')->where('examiner_id', $me)->whereIn('status', ['passed', 'failed'])
            ->orderByDesc('recorded_at')->limit(self::RECENT)->get();

        return response()->json(['data' => [
            'booked' => $booked->map(fn (QuranExam $e) => $this->present->exam($e))->sortBy(fn (array $e) => $e['lesson']['starts_at']['utc'] ?? '')->values()->all(),
            'recent' => $recent->map(fn (QuranExam $e) => $this->present->exam($e))->all(),
            'mistake_types' => $program->mistakeTypes(),
            'grades' => $program->grades(),
        ]]);
    }

    /**
     * Record an exam's result.
     *
     * The examiner alone, from the assessment lesson's opening on, once: the mistakes by kind (mistakes: [{kind,
     * count}], else the words marked in the lesson's mushaf) and their own grade with a note when it differs. Passed
     * at the program's pass grade; a fail needs note (what to work on) and retake_on (a day after today, at most 180
     * days ahead), and the retake is suggested for that day. A pass earns a certificate when the academy gives them.
     */
    public function record(Request $request, string $exam, RecordQuranExam $record): JsonResponse
    {
        $request->validate([
            'mistakes' => ['nullable', 'array', 'max:20'],
            'mistakes.*.kind' => ['required', 'string', 'max:30'],
            'mistakes.*.count' => ['required', 'integer', 'between:0,500'],
            'grade' => ['nullable', Rule::in(Grades::ORDER)],
            'note' => ['nullable', 'string', 'max:'.RecordQuranExam::NOTE_MAX],
            'retake_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], trans('quran::exams.attributes'));
        $found = $this->find($exam);
        $me = (string) $request->user()?->getAuthIdentifier();
        abort_unless($found->examiner_id === $me, 403);
        $input = $request->only(['grade', 'note', 'retake_on']);
        if (is_array($request->input('mistakes'))) {
            $counts = [];
            foreach ($request->input('mistakes') as $mistake) {
                $counts[(string) $mistake['kind']] = ($counts[(string) $mistake['kind']] ?? 0) + (int) $mistake['count'];
            }
            $input['mistakes'] = $counts;
        }

        return response()->json(['data' => $this->present->exam($record->handle($found, $me, $input))], 201);
    }

    /**
     * A learner's Quran exams.
     *
     * Every exam of the learner but the cancelled ones, newest first: the stop, whether it is waiting to be booked,
     * booked (with the lesson's time and the examiner), passed or failed (the grade, the mistakes by kind except in
     * the kids' look, the examiner's words and the day to try again).
     */
    public function family(Request $request, FamilyView $family): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $viewer = (string) $request->user()?->getAuthIdentifier();
        $asked = $request->query('learner_id', $request->input('learner_id'));
        $learner = $family->learner($viewer, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;
        $counts = $family->stage($viewer) !== LearnerStage::Kids;
        $exams = QuranExam::query()->with('program')->where('student_id', $learner)->where('status', '<>', 'cancelled')->orderByDesc('created_at')->get();

        return response()->json(['data' => [
            'learner_id' => $learner,
            'exams' => $exams->map(fn (QuranExam $e) => $this->present->exam($e, family: true, counts: $counts))->all(),
        ]]);
    }

    private function find(string $exam): QuranExam
    {
        return (Str::isUuid($exam) ? QuranExam::query()->find($exam) : null) ?? throw new NotFoundHttpException;
    }
}
