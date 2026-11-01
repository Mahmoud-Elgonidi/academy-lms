<?php

namespace Modules\Quran\Actions\Lessons;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Contracts\LearningApi;
use Modules\Quran\Actions\Exams\SuggestQuranExam;
use Modules\Quran\Actions\Hifz\RecordHifzReview;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Domain\Hifz\Scheduler;
use Modules\Quran\Domain\Programs\Portion;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Mushaf\MushafLessons;
use Modules\Quran\Programs\Mushaf;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;

/**
 * The teacher records a Quran lesson (docs/architecture/02, section 26), for
 * each student in it: each part of today's portion they recited (new, near
 * review, far review) with its mistakes by kind (as given, else the words
 * marked in the lesson's shared mushaf on that part's verses), its grade
 * worked out from them (Domain\Grading) or the teacher's own with a note.
 *
 * - The position moves past the new part only when it meets the program's
 *   pass grade (a group's when most of its graded students met it), and
 *   never while the course is paused or over (or the group ended): the
 *   result is kept, nothing moves. A new part below it comes back.
 * - The far review moves on past the pages reviewed.
 * - The hifz map hears each page once (source lesson): the new part's
 *   pages (on memorizing tracks) memorized when it passed and nothing of
 *   the page is left to learn, learning when something is, forgotten when
 *   it comes back; the reviews' by their grade. A page this lesson's report
 *   or marks already reviewed is left.
 *
 * - Past a stop of the program (the end of a part or a surah, or the
 *   program's end), each student whose new part passed has an exam
 *   suggested while the academy holds exams (SuggestQuranExam).
 *
 * One result per lesson and student, never changed. The lesson's teacher
 * (a substitute too) records it, from the room's opening on, unless it was
 * cancelled. A course of a Quran subject without a plan has nothing to record.
 */
final class RecordQuranLesson
{
    public const PARTS = ['new', 'near', 'far'];

    public const NOTE_MAX = 500;

    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly Plans $plans,
        private readonly Mushaf $mushaf,
        private readonly RecordHifzReview $review,
        private readonly QuranArea $area,
        private readonly LearningApi $learning,
        private readonly SuggestQuranExam $exams,
    ) {}

    /**
     * @param  array{students?: list<array{student_id?: ?string, parts: array<string, array{mistakes?: array<string, int>, grade?: ?string, note?: ?string}>}>, student_id?: ?string, parts?: array<string, mixed>}  $input
     * @return list<QuranLessonResult>
     */
    public function handle(string $sessionId, string $actorId, array $input, ?CarbonImmutable $now = null): array
    {
        $this->area->require();
        $now ??= CarbonImmutable::now();
        $card = Str::isUuid($sessionId) ? $this->attendance->lesson($sessionId) : null;
        if ($card === null) {
            throw ValidationException::withMessages(['session' => __('quran::programs.errors.lesson')]);
        }
        if ($card->teacherId !== $actorId) {
            throw new AuthorizationException(__('quran::programs.errors.teacher'));
        }
        if ($card->status === 'cancelled' || $now->lessThan($card->startsAt->subMinutes(MushafLessons::OPENS_BEFORE_MIN))) {
            throw ValidationException::withMessages(['session' => __('quran::programs.errors.not_now')]);
        }
        $students = $this->students($card, $input);
        $settings = QuranSettings::current();

        return DB::transaction(function () use ($card, $students, $settings, $actorId, $now): array {
            $plan = $this->plans->forLesson($card);
            $plan = $plan === null ? null : QuranPlan::query()->with('program')->lockForUpdate()->find($plan->id);
            if ($plan === null) {
                throw ValidationException::withMessages(['session' => __('quran::programs.errors.no_plan')]);
            }
            $portion = $this->plans->portion($plan) ?? throw ValidationException::withMessages(['session' => __('quran::programs.errors.no_text')]);
            $graded = [];
            foreach ($students as $i => ['student_id' => $studentId, 'parts' => $parts]) {
                if (QuranLessonResult::query()->where('session_id', $card->id)->where('student_id', $studentId)->exists()) {
                    throw ValidationException::withMessages(["students.{$i}" => __('quran::programs.errors.recorded')]);
                }
                $graded[$studentId] = $this->grade($card->id, $studentId, $parts, $portion, $settings, "students.{$i}");
            }

            $shape = $plan->shape();
            $newGrades = array_values(array_filter(array_map(fn (array $p) => $p['new']['grade'] ?? null, $graded)));
            $passed = count(array_filter($newGrades, fn (string $g) => Grades::meets($g, $shape->passGrade)));
            $frozen = $this->plans->frozen($plan, $card->startsAt) !== null;
            $advance = ! $frozen && $portion->has('new') && $newGrades !== [] && $passed * 2 > count($newGrades);
            $before = $plan->position();
            if ($advance) {
                $plan->fill(QuranPlan::positionColumns($portion->next));
                $plan->finished_at = $portion->next === null ? $now : null;
            }
            $farReviewed = array_filter($graded, fn (array $p) => isset($p['far']));
            if (! $frozen && $farReviewed !== [] && $portion->farPages !== []) {
                $plan->far_cursor = $portion->farPages[array_key_last($portion->farPages)];
            }
            $plan->save();

            $results = [];
            foreach ($graded as $studentId => $parts) {
                $result = QuranLessonResult::query()->create([
                    'session_id' => $card->id, 'student_id' => $studentId, 'enrollment_id' => $this->enrollmentOf($card, $studentId),
                    'plan_id' => $plan->id, 'recorded_by' => $actorId, 'parts' => $parts, 'new_grade' => $parts['new']['grade'] ?? null,
                    'advanced' => $advance, 'frozen' => $frozen, 'position_before' => $before?->toArray(), 'position_after' => $plan->position()?->toArray(),
                    'recorded_at' => $now,
                ]);
                $this->hifz($result, $parts, $portion, $shape->memorizes(), $shape->passGrade, $card);
                $results[] = $result;
            }
            // Past a stop of the program (or its end), each student whose new part passed has an exam suggested.
            $this->exams->afterLesson($plan, $portion, $results, $actorId);

            return $results;
        });
    }

    /**
     * Who is recorded: the students given (each seated in the lesson), or a one-to-one lesson's student.
     *
     * @return list<array{student_id: string, parts: array<string, array<string, mixed>>}>
     */
    private function students(LessonCard $card, array $input): array
    {
        $students = isset($input['students']) ? $input['students'] : [['student_id' => $input['student_id'] ?? null, 'parts' => $input['parts'] ?? []]];
        Validator::make(['students' => $students], [
            'students' => ['required', 'array', 'min:1', 'max:20'],
            'students.*.student_id' => ['nullable', 'string', 'max:64'],
            'students.*.parts' => ['required', 'array', 'min:1'],
            'students.*.parts.*' => ['array'],
            'students.*.parts.*.mistakes' => ['nullable', 'array', 'max:20'],
            'students.*.parts.*.mistakes.*' => ['integer', 'between:0,200'],
            'students.*.parts.*.grade' => ['nullable', Rule::in(Grades::ORDER)],
            'students.*.parts.*.note' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
        ], [], trans('quran::programs.attributes'))->validate();
        $seen = [];
        foreach ($students as $i => $student) {
            $id = $student['student_id'] ?? ($card->isGroup() ? null : $card->studentId);
            if ($id === null || ! $card->hasStudent($id) || isset($seen[$id])) {
                throw ValidationException::withMessages(["students.{$i}.student_id" => __('quran::programs.errors.student')]);
            }
            if (array_diff(array_keys($student['parts']), self::PARTS) !== []) {
                throw ValidationException::withMessages(["students.{$i}.parts" => __('quran::programs.errors.part')]);
            }
            $seen[$id] = true;
            $students[$i] = ['student_id' => $id, 'parts' => $student['parts']];
        }

        return array_values($students);
    }

    /**
     * Each part recited, graded.
     *
     * @param  array<string, array<string, mixed>>  $parts
     * @return array<string, array{ranges: list<array{surah: int, from: int, to: int}>, pages: list<int>, lesson: ?int, mistakes: array<string, int>, penalty: float, computed: string, grade: string, note: ?string}>
     */
    private function grade(string $sessionId, string $studentId, array $parts, Portion $portion, QuranSettings $settings, string $key): array
    {
        $kinds = $settings->mistakeKinds();
        $graded = [];
        foreach (self::PARTS as $part) {
            if (! isset($parts[$part])) {
                continue;
            }
            if (! $portion->has($part)) {
                throw ValidationException::withMessages(["{$key}.parts.{$part}" => __('quran::programs.errors.not_in_portion')]);
            }
            $given = $parts[$part];
            $ranges = $portion->part($part);
            $counts = isset($given['mistakes']) ? array_map('intval', $given['mistakes']) : $this->marks($sessionId, $studentId, $ranges);
            if (array_diff(array_keys($counts), $kinds) !== []) {
                throw ValidationException::withMessages(["{$key}.parts.{$part}.mistakes" => __('quran::programs.errors.kind')]);
            }
            $counts = array_filter($counts);
            $pages = $ranges === [] ? Grading::MIN_PAGES : (float) $this->mushaf->layout()?->pagesIn($ranges);
            $computed = Grading::grade($counts, $settings->weights(), $settings->gradeScale(), $pages);
            $note = trim((string) ($given['note'] ?? '')) ?: null;
            $decided = Grading::decide($computed['grade'], $given['grade'] ?? null, $note);
            if ($decided['error'] !== null) {
                throw ValidationException::withMessages(["{$key}.parts.{$part}.".($decided['error'] === 'note' ? 'note' : 'grade') => __('quran::programs.errors.override_'.$decided['error'])]);
            }
            $graded[$part] = [
                'ranges' => array_map(fn (VerseRange $r) => ['surah' => $r->surah, 'from' => $r->from, 'to' => $r->to], $ranges),
                'pages' => $portion->pagesOf($part),
                'lesson' => $part === 'new' ? $portion->lesson : null,
                'mistakes' => $counts,
                'penalty' => $computed['penalty'],
                'computed' => $computed['grade'],
                'grade' => $decided['grade'],
                'note' => $note,
            ];
        }

        return $graded;
    }

    /**
     * The words marked in the lesson's shared mushaf on these verses, by kind.
     *
     * @param  list<VerseRange>  $ranges
     * @return array<string, int>
     */
    private function marks(string $sessionId, string $studentId, array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        return RecitationMistake::query()->where('session_id', $sessionId)->where('student_id', $studentId)
            ->where(function ($q) use ($ranges) {
                foreach ($ranges as $range) {
                    $q->orWhere(fn ($r) => $r->where('surah', $range->surah)->whereBetween('verse', [$range->from, $range->to]));
                }
            })
            ->groupBy('kind')->selectRaw('kind, count(*) as n')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all();
    }

    private function enrollmentOf(LessonCard $card, string $studentId): ?string
    {
        if (! $card->isGroup()) {
            return $card->enrollmentId;
        }
        $id = DB::table('session_participants')->where('session_id', $card->id)->where('user_id', $studentId)->value('enrollment_id');

        return $id === null ? null : (string) $id;
    }

    /**
     * The hifz map hears each page of what was recited once (source lesson, by this result).
     *
     * @param  array<string, array<string, mixed>>  $parts
     */
    private function hifz(QuranLessonResult $result, array $parts, Portion $portion, bool $memorizes, string $passGrade, LessonCard $card): void
    {
        $done = $this->reviewedByLesson($card->id, $result->student_id);
        foreach (self::PARTS as $part) {
            if (! isset($parts[$part]) || ($part === 'new' && ! $memorizes)) {
                continue;
            }
            $grade = $parts[$part]['grade'];
            $passes = Grades::meets($grade, $passGrade);
            foreach ($portion->pagesOf($part) as $page) {
                if (isset($done[$page])) {
                    continue;
                }
                $done[$page] = true;
                [$hifzGrade, $memorized] = $part === 'new'
                    ? [$passes ? Grades::hifzGrade($grade) : Scheduler::AGAIN, $passes && in_array($page, $portion->completedPages, true)]
                    : [Grades::hifzGrade($grade), false];
                $this->review->handle($result->student_id, $page, $hifzGrade, 'lesson', $result->id, $card->startsAt, $memorized);
            }
        }
    }

    /**
     * The pages this lesson's report or marked words already reviewed for the student.
     *
     * @return array<int, true>
     */
    private function reviewedByLesson(string $sessionId, string $studentId): array
    {
        $reports = $this->learning->reportIdsOf($sessionId);

        return DB::table('hifz_reviews')->where('student_id', $studentId)
            ->where(fn ($q) => $q->where(fn ($r) => $r->where('source', 'report')->whereIn('source_id', $reports))
                ->orWhere(fn ($r) => $r->where('source', 'mistakes')->whereIn('source_id', [...$reports, $sessionId])))
            ->pluck('page')->mapWithKeys(fn ($p) => [(int) $p => true])->all();
    }
}
