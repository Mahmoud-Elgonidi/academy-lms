<?php

namespace Modules\Quran\Actions\Exams;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Events\ExamBooked;
use Modules\Quran\Exams\ExaminerCheck;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Contracts\AssessmentLessons;
use Modules\Scheduling\Contracts\DTOs\AssessmentBooking;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Books a suggested exam (docs/architecture/02, section 26) as an
 * assessment lesson with an examiner the academy's choice allows
 * (ExaminerCheck: never the course's own teacher unless "any teacher"),
 * through Scheduling's AssessmentLessons, so its rules hold: the course
 * running and not paused or over that day, an examiner cleared for a
 * student under 18, nobody booked twice at once. A retake is not booked
 * before its day. Audited by the stop alone.
 */
final class BookQuranExam
{
    public function __construct(
        private readonly QuranArea $area,
        private readonly ExaminerCheck $examiners,
        private readonly AssessmentLessons $lessons,
        private readonly Plans $plans,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(QuranExam $exam, string $examinerId, CarbonImmutable $startsAt, int $durationMin, string $actorId): QuranExam
    {
        $this->area->require();
        if (! QuranSettings::current()->exams_enabled) {
            throw ValidationException::withMessages(['exam' => __('quran::exams.errors.off')]);
        }

        return DB::transaction(function () use ($exam, $examinerId, $startsAt, $durationMin, $actorId) {
            $exam = QuranExam::query()->with('plan')->lockForUpdate()->findOrFail($exam->id);
            if ($exam->status !== 'suggested') {
                throw ValidationException::withMessages(['exam' => __('quran::exams.errors.not_suggested')]);
            }
            if ($exam->enrollment_id === null) {
                throw ValidationException::withMessages(['exam' => __('quran::exams.errors.no_course')]);
            }
            if ($exam->not_before !== null && $startsAt->setTimezone($this->plans->timezone())->toDateString() < $exam->not_before->toDateString()) {
                throw ValidationException::withMessages(['starts_at' => __('quran::exams.errors.before_retake', ['day' => $exam->not_before->locale(app()->getLocale())->translatedFormat('j F Y')])]);
            }
            $this->examiners->assert($exam, $examinerId);
            $sessionId = $this->lessons->book(new AssessmentBooking($exam->enrollment_id, $examinerId, $startsAt, $durationMin, $actorId));
            $exam->fill(['status' => 'booked', 'examiner_id' => $examinerId, 'session_id' => $sessionId, 'booked_at' => CarbonImmutable::now(), 'booked_by' => $actorId])->save();
            $this->audit->record('quran.exam_booked', 'quran_exam', $exam->id, ['stop_kind' => $exam->stop_kind, 'stop_number' => $exam->stop_number, 'session_id' => $sessionId]);
            ExamBooked::dispatch($exam->id);

            return $exam;
        });
    }
}
