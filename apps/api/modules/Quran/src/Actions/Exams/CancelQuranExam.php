<?php

namespace Modules\Quran\Actions\Exams;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Contracts\AssessmentLessons;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Cancels an exam that is suggested or booked (docs/architecture/02,
 * section 26), with a reason kept on the exam (never in the audit); a
 * booked one's assessment lesson is cancelled with it, for the academy (no
 * credit moves). The stop may be suggested again by a later lesson.
 */
final class CancelQuranExam
{
    public function __construct(
        private readonly QuranArea $area,
        private readonly AssessmentLessons $lessons,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(QuranExam $exam, string $actorId, string $reason): QuranExam
    {
        $this->area->require();
        $reason = trim(strip_tags($reason));
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:300']], [], trans('quran::exams.attributes'))->validate();

        return DB::transaction(function () use ($exam, $actorId, $reason) {
            $exam = QuranExam::query()->lockForUpdate()->findOrFail($exam->id);
            if (! $exam->isOpen()) {
                throw ValidationException::withMessages(['exam' => __('quran::exams.errors.not_open')]);
            }
            if ($exam->session_id !== null) {
                $this->lessons->cancel($exam->session_id, $actorId);
            }
            $exam->fill(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now(), 'cancel_reason' => $reason, 'cancelled_by' => $actorId])->save();
            $this->audit->record('quran.exam_cancelled', 'quran_exam', $exam->id, ['stop_kind' => $exam->stop_kind, 'stop_number' => $exam->stop_number]);

            return $exam;
        });
    }
}
