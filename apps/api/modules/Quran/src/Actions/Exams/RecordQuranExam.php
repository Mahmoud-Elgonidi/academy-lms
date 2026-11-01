<?php

namespace Modules\Quran\Actions\Exams;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Actions\Certificates\IssueQuranCertificate;
use Modules\Quran\Domain\Exams\ExamOutcome;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Events\ExamDecided;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Mushaf\MushafLessons;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The examiner records an exam's result (docs/architecture/02, section 26),
 * they alone, from the assessment lesson's opening on: the mistakes by kind
 * (as given, else the words they marked in the lesson's shared mushaf),
 * graded over the stop's pages by Domain\Grading, or their own grade with a
 * note. Passed at the program's pass grade; a fail needs a note and the day
 * to try again, and the retake is suggested for that day. A pass earns a
 * certificate when the academy gives them and the program counts for one.
 * Once; audited by the stop and the outcome.
 */
final class RecordQuranExam
{
    public const NOTE_MAX = 1000;

    public function __construct(
        private readonly QuranArea $area,
        private readonly AttendanceApi $attendance,
        private readonly Stops $stops,
        private readonly Plans $plans,
        private readonly SuggestQuranExam $suggest,
        private readonly IssueQuranCertificate $certificates,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  array{mistakes?: ?array<string, int>, grade?: ?string, note?: ?string, retake_on?: ?string}  $input */
    public function handle(QuranExam $exam, string $actorId, array $input, ?CarbonImmutable $now = null): QuranExam
    {
        $this->area->require();
        $now ??= CarbonImmutable::now();
        Validator::make($input, [
            'mistakes' => ['nullable', 'array', 'max:20'],
            'mistakes.*' => ['integer', 'between:0,500'],
            'grade' => ['nullable', Rule::in(Grades::ORDER)],
            'note' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
            'retake_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], trans('quran::exams.attributes'))->validate();

        return DB::transaction(function () use ($exam, $actorId, $input, $now) {
            $exam = QuranExam::query()->with('program')->lockForUpdate()->findOrFail($exam->id);
            if ($exam->examiner_id !== $actorId) {
                throw new AuthorizationException(__('quran::exams.errors.not_examiner'));
            }
            if ($exam->status !== 'booked') {
                throw ValidationException::withMessages(['exam' => __('quran::exams.errors.not_booked')]);
            }
            $lesson = $this->attendance->lesson((string) $exam->session_id);
            if ($lesson === null || $lesson->status === 'cancelled' || $now->lessThan($lesson->startsAt->subMinutes(MushafLessons::OPENS_BEFORE_MIN))) {
                throw ValidationException::withMessages(['exam' => __('quran::exams.errors.not_now')]);
            }

            $settings = QuranSettings::current();
            $counts = isset($input['mistakes']) ? array_map('intval', $input['mistakes']) : $this->marks($exam);
            if (array_diff(array_keys($counts), $settings->mistakeKinds()) !== []) {
                throw ValidationException::withMessages(['mistakes' => __('quran::programs.errors.kind')]);
            }
            $counts = array_filter($counts);
            $computed = Grading::grade($counts, $settings->weights(), $settings->gradeScale(), $this->stops->pages($exam->program, $exam->stop_kind, $exam->stop_number));
            $note = trim((string) ($input['note'] ?? '')) ?: null;
            $decided = Grading::decide($computed['grade'], $input['grade'] ?? null, $note);
            if ($decided['error'] !== null) {
                throw ValidationException::withMessages([$decided['error'] === 'note' ? 'note' : 'grade' => __('quran::programs.errors.override_'.$decided['error'])]);
            }
            $passed = ExamOutcome::passed($decided['grade'], $exam->program->pass_grade);
            $retakeOn = ! $passed && isset($input['retake_on']) ? CarbonImmutable::parse($input['retake_on']) : null;
            $missing = ExamOutcome::missing($passed, $note, $retakeOn, SuggestQuranExam::today($this->plans->timezone(), $now));
            if ($missing !== []) {
                throw ValidationException::withMessages(array_combine($missing, array_map(fn (string $m) => __('quran::exams.errors.fail_'.$m, ['days' => ExamOutcome::RETAKE_MAX_DAYS]), $missing)));
            }

            $exam->fill([
                'status' => $passed ? 'passed' : 'failed', 'grade' => $decided['grade'], 'computed' => $computed['grade'], 'penalty' => $computed['penalty'],
                'mistakes' => $counts, 'result_note' => $note, 'retake_on' => $retakeOn?->toDateString(), 'recorded_at' => $now,
            ])->save();
            $this->audit->record('quran.exam_recorded', 'quran_exam', $exam->id, [
                'stop_kind' => $exam->stop_kind, 'stop_number' => $exam->stop_number, 'outcome' => $exam->status, 'grade' => $exam->grade,
            ]);
            ExamDecided::dispatch($exam->id);

            if (! $passed && $settings->exams_enabled) {
                $this->suggest->retake($exam, $actorId);
            }
            if ($passed && $settings->certificates_enabled && $exam->program->counts_for_certificate) {
                $this->certificates->handle($exam, $actorId);
            }

            return $exam;
        });
    }

    /**
     * The words the examiner marked for the student in the assessment lesson's mushaf, by kind.
     *
     * @return array<string, int>
     */
    private function marks(QuranExam $exam): array
    {
        return RecitationMistake::query()->where('session_id', $exam->session_id)->where('student_id', $exam->student_id)
            ->groupBy('kind')->selectRaw('kind, count(*) as n')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all();
    }
}
