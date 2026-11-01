<?php

namespace Modules\Quran\Actions\Exams;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Programs\Portion;
use Modules\Quran\Events\ExamSuggested;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Suggests a learner's exam at a stop of their plan's program
 * (docs/architecture/02, section 26), while the academy holds exams: the
 * end of a part or a surah (the program's exam_stops), the whole program
 * (a khatma when it is the whole Quran). One open a plan, student and stop
 * (held by the database), none again once that stop is passed on the same
 * program; a fail's retake is suggested for its day (not_before). Audited
 * by the stop alone.
 *
 * afterLesson() is RecordQuranLesson's: a lesson that moved the plan past
 * stops suggests them for each student whose new part met the pass grade,
 * and the program's end once the scope is done.
 */
final class SuggestQuranExam
{
    public function __construct(
        private readonly QuranArea $area,
        private readonly Stops $stops,
        private readonly AuditTrail $audit,
    ) {}

    /** The exam open at this stop (made now, or the one already open); null when the stop is passed already. */
    public function handle(QuranPlan $plan, string $studentId, ?string $enrollmentId, string $kind, ?int $number, ?string $actorId = null, ?QuranExam $retakeOf = null): ?QuranExam
    {
        $this->area->require();
        if (! QuranSettings::current()->exams_enabled) {
            throw ValidationException::withMessages(['exam' => __('quran::exams.errors.off')]);
        }
        $number = in_array($kind, ['program', 'khatma'], true) ? null : $number;
        if (! in_array($kind, QuranExam::KINDS, true) || ($kind === 'juz' && ($number < 1 || $number > 30)) || ($kind === 'surah' && ($number < 1 || $number > 114))) {
            throw ValidationException::withMessages(['stop' => __('quran::exams.errors.stop')]);
        }
        $same = fn () => QuranExam::query()->where('student_id', $studentId)->where('stop_kind', $kind)
            ->when($number === null, fn ($q) => $q->whereNull('stop_number'), fn ($q) => $q->where('stop_number', $number));
        $open = $same()->where('plan_id', $plan->id)->whereIn('status', QuranExam::OPEN)->first();
        if ($open !== null) {
            return $open;
        }
        if ($same()->where('program_id', $plan->program_id)->where('status', 'passed')->exists()) {
            return null;
        }

        try {
            $exam = DB::transaction(fn () => QuranExam::query()->create([
                'plan_id' => $plan->id, 'program_id' => $plan->program_id, 'student_id' => $studentId, 'enrollment_id' => $enrollmentId,
                'stop_kind' => $kind, 'stop_number' => $number, 'status' => 'suggested', 'retake_of' => $retakeOf?->id,
                'not_before' => $retakeOf?->retake_on, 'created_by' => $actorId,
            ]));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'quran_exams_one_open')) {
                return $same()->where('plan_id', $plan->id)->whereIn('status', QuranExam::OPEN)->first();
            }

            throw $e;
        }
        $this->audit->record('quran.exam_suggested', 'quran_exam', $exam->id, ['stop_kind' => $kind, 'stop_number' => $number, 'retake' => $retakeOf !== null]);
        ExamSuggested::dispatch($exam->id);

        return $exam;
    }

    /**
     * The exams a recorded lesson reaches.
     *
     * @param  list<QuranLessonResult>  $results
     * @return list<QuranExam>
     */
    public function afterLesson(QuranPlan $plan, Portion $portion, array $results, string $actorId): array
    {
        if (! QuranSettings::current()->exams_enabled) {
            return [];
        }
        $program = $plan->program;
        $stops = array_map(fn (array $s) => [$s['kind'], $s['number']], $portion->stops);
        if ($portion->next === null && ($program->exam_stops !== 'none' || $program->counts_for_certificate)) {
            $stops[] = [$this->stops->completion($program), null];
        }
        $exams = [];
        foreach ($results as $result) {
            if (! $result->advanced || $result->new_grade === null || ! Grades::meets($result->new_grade, $plan->shape()->passGrade)) {
                continue;
            }
            foreach ($stops as [$kind, $number]) {
                $exam = $this->handle($plan, $result->student_id, $result->enrollment_id, $kind, $number, $actorId);
                if ($exam !== null) {
                    $exams[] = $exam;
                }
            }
        }

        return $exams;
    }

    /** A fail's retake, for its day. */
    public function retake(QuranExam $failed, string $actorId): ?QuranExam
    {
        $plan = QuranPlan::query()->with('program')->findOrFail((string) $failed->plan_id);

        return $this->handle($plan, $failed->student_id, $failed->enrollment_id, $failed->stop_kind, $failed->stop_number, $actorId, $failed);
    }

    /** Today on the academy's calendar (a retake's day is compared with it). */
    public static function today(string $timezone, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfDay();
    }
}
