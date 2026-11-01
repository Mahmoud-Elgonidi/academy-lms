<?php

namespace Modules\Quran\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Programs\Plans;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A Quran lesson for its teacher (a substitute too): today's portion from
 * the plan (new, near review, far review), the academy's mistake types and
 * grades, and recording what each student recited.
 */
final class QuranLessonController
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly Plans $plans,
        private readonly ProgramPresenter $present,
    ) {}

    /**
     * A Quran lesson's portion.
     *
     * The plan the lesson follows (its course's, or its group's) with today's portion: the new part, the near and
     * far reviews (passages by surah and verses, and their pages), the exam stops it reaches, and whether the plan
     * is frozen (the course paused or over). plan is null for a course with no Quran plan: it has no portion. With
     * the academy's mistake types and grades, and what was already recorded for each student.
     */
    public function show(Request $request, string $session): JsonResponse
    {
        $card = $this->lesson($request, $session);
        $plan = $this->plans->forLesson($card);
        $students = $card->students !== [] ? $card->students : [['id' => (string) $card->studentId, 'name' => $card->studentName, 'first_name' => $card->studentName]];

        return response()->json(['data' => [
            'session_id' => $card->id,
            'group' => $card->isGroup(),
            'students' => array_map(fn (array $s) => ['id' => $s['id'], 'name' => $s['name']], $students),
            'plan' => $plan === null ? null : $this->present->plan($plan, $this->plans->portion($plan), $this->plans->frozen($plan, $card->startsAt)),
            'mistake_types' => $this->present->mistakeTypes(),
            'grades' => $this->present->grades(),
            'results' => QuranLessonResult::query()->where('session_id', $card->id)->orderBy('created_at')->get()
                ->map(fn (QuranLessonResult $r) => $this->present->result($r))->all(),
        ]]);
    }

    /**
     * Record a Quran lesson.
     *
     * For each student (students[].student_id; a one-to-one lesson may leave it out), each part of today's portion
     * they recited (parts.new, parts.near, parts.far) with its mistakes by kind (mistakes: [{kind, count}], else the
     * words marked in the lesson's mushaf), and the teacher's own grade with a note when it differs from the mistakes'. The
     * position moves past the new part only when it reaches the program's pass grade (a group's: most of its
     * students), and never while the course is paused or over. Once per lesson and student.
     */
    public function record(Request $request, string $session, RecordQuranLesson $record): JsonResponse
    {
        $request->validate([
            'students' => ['nullable', 'array', 'min:1', 'max:20'],
            'students.*.student_id' => ['nullable', 'string', 'max:64'],
            'students.*.parts' => ['required_with:students', 'array', 'min:1'],
            'student_id' => ['nullable', 'string', 'max:64'],
            'parts' => ['nullable', 'array'],
            'students.*.parts.new' => ['nullable', 'array'],
            'students.*.parts.new.mistakes' => ['nullable', 'array', 'max:20'],
            'students.*.parts.new.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'students.*.parts.new.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'students.*.parts.new.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'students.*.parts.new.note' => ['nullable', 'string', 'max:500'],
            'students.*.parts.near' => ['nullable', 'array'],
            'students.*.parts.near.mistakes' => ['nullable', 'array', 'max:20'],
            'students.*.parts.near.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'students.*.parts.near.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'students.*.parts.near.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'students.*.parts.near.note' => ['nullable', 'string', 'max:500'],
            'students.*.parts.far' => ['nullable', 'array'],
            'students.*.parts.far.mistakes' => ['nullable', 'array', 'max:20'],
            'students.*.parts.far.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'students.*.parts.far.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'students.*.parts.far.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'students.*.parts.far.note' => ['nullable', 'string', 'max:500'],
            'parts.new' => ['nullable', 'array'],
            'parts.new.mistakes' => ['nullable', 'array', 'max:20'],
            'parts.new.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'parts.new.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'parts.new.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'parts.new.note' => ['nullable', 'string', 'max:500'],
            'parts.near' => ['nullable', 'array'],
            'parts.near.mistakes' => ['nullable', 'array', 'max:20'],
            'parts.near.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'parts.near.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'parts.near.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'parts.near.note' => ['nullable', 'string', 'max:500'],
            'parts.far' => ['nullable', 'array'],
            'parts.far.mistakes' => ['nullable', 'array', 'max:20'],
            'parts.far.mistakes.*.kind' => ['required', 'string', 'max:30'],
            'parts.far.mistakes.*.count' => ['required', 'integer', 'between:0,200'],
            'parts.far.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'parts.far.note' => ['nullable', 'string', 'max:500'],
        ], [], trans('quran::programs.attributes'));
        $card = $this->lesson($request, $session);
        $results = $record->handle($card->id, (string) $request->user()->getAuthIdentifier(), self::input($request->only(['students', 'student_id', 'parts'])));
        $plan = $this->plans->forLesson($card);

        return response()->json(['data' => [
            'results' => array_map(fn (QuranLessonResult $r) => $this->present->result($r), $results),
            'plan' => $plan === null ? null : $this->present->plan($plan, $this->plans->portion($plan), $this->plans->frozen($plan)),
        ]], 201);
    }

    /**
     * Mistakes come as a list of {kind, count}; the action counts them by kind.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function input(array $input): array
    {
        $parts = function (array $parts): array {
            foreach ($parts as $name => $part) {
                if (is_array($part) && is_array($part['mistakes'] ?? null)) {
                    $counts = [];
                    foreach ($part['mistakes'] as $mistake) {
                        $counts[(string) $mistake['kind']] = ($counts[(string) $mistake['kind']] ?? 0) + (int) $mistake['count'];
                    }
                    $parts[$name]['mistakes'] = $counts;
                }
            }

            return $parts;
        };
        if (isset($input['students'])) {
            $input['students'] = array_map(fn (array $s) => ['parts' => $parts((array) ($s['parts'] ?? []))] + $s, $input['students']);
        }
        if (isset($input['parts'])) {
            $input['parts'] = $parts((array) $input['parts']);
        }

        return $input;
    }

    private function lesson(Request $request, string $session): LessonCard
    {
        $card = Str::isUuid($session) ? $this->attendance->lesson($session) : null;
        if ($card === null) {
            throw new NotFoundHttpException;
        }
        abort_unless($card->teacherId === $request->user()?->getAuthIdentifier(), 403);

        return $card;
    }
}
