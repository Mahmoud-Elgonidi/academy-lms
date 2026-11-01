<?php

namespace Modules\Quran\Http\V1\Resources;

use Illuminate\Support\Facades\DB;
use Modules\Quran\Certificates\CertificateLinks;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Exams and certificates as the API answers them: the stop by name, the
 * lesson's time, the grade and mistakes by kind (none in the kids' look),
 * the examiner's words to the learner; a family never reads why an exam was
 * cancelled. A certificate with its signed page while it stands.
 */
final class ExamPresenter
{
    public function __construct(
        private readonly Stops $stops,
        private readonly AttendanceApi $lessons,
        private readonly LocalTimePresenter $time,
        private readonly Certificates $certificates,
        private readonly CertificateLinks $links,
    ) {}

    /**
     * @param  bool  $family  a family reads no cancel reason; $counts false: the kids' look, no counts either
     * @return array<string, mixed>
     */
    public function exam(QuranExam $exam, bool $family = false, bool $counts = true): array
    {
        $exam->loadMissing('program');
        $lesson = $exam->session_id === null ? null : $this->lessons->lesson($exam->session_id);
        $settings = QuranSettings::current();

        return [
            'id' => $exam->id,
            'student_id' => $exam->student_id,
            'student' => $family ? null : $this->name($exam->student_id),
            'enrollment_id' => $exam->enrollment_id,
            'program' => ['id' => $exam->program_id, 'name' => $exam->program->name],
            /** juz, surah, program or khatma, and the part's or surah's number */
            'stop' => ['kind' => $exam->stop_kind, 'number' => $exam->stop_number, 'label' => $this->stops->label($exam->stop_kind, $exam->stop_number, $exam->program->name)],
            /** suggested, booked, passed, failed or cancelled */
            'status' => $exam->status,
            'status_label' => __('quran::exams.statuses.'.$exam->status),
            /** A retake (of a failed exam) is not booked before this day. */
            'retake' => $exam->retake_of !== null,
            'not_before' => $exam->not_before?->toDateString(),
            'examiner' => $exam->examiner_id === null ? null : ['id' => $exam->examiner_id, 'name' => $this->name($exam->examiner_id)],
            'lesson' => $lesson === null ? null : ['session_id' => $lesson->id, 'status' => $lesson->status, 'starts_at' => $this->time->present($lesson->startsAt), 'ends_at' => $this->time->present($lesson->endsAt)],
            'result' => $exam->grade === null ? null : [
                /** excellent, very_good, good, acceptable or repeat */
                'grade' => $exam->grade,
                'grade_label' => __('quran::programs.grades.'.$exam->grade),
                'passed' => $exam->status === 'passed',
                'mistakes' => $counts ? collect($exam->mistakes ?? [])->map(fn ($n, $kind) => ['kind' => $kind, 'label' => $settings->mistakeName((string) $kind), 'count' => (int) $n])->values()->all() : [],
                /** The examiner's words to the learner (a fail always has them). */
                'note' => $exam->result_note,
                'retake_on' => $exam->retake_on?->toDateString(),
                'recorded_at' => $exam->recorded_at === null ? null : $this->time->present($exam->recorded_at),
            ] + ($family ? [] : ['computed' => $exam->computed]),
        ] + ($family ? [] : ['cancel_reason' => $exam->cancel_reason]);
    }

    /** @return array<string, mixed> */
    public function certificate(QuranCertificate $certificate, ?string $readerId): array
    {
        return [
            'id' => $certificate->id,
            'number' => $certificate->number,
            'title' => $this->certificates->title($certificate),
            /** juz, surah, program or khatma, and the part's or surah's number */
            'portion' => ['kind' => $certificate->portion['kind'], 'number' => $certificate->portion['number'] ?? null, 'label' => $this->certificates->portion($certificate)],
            'riwaya' => __('quran::settings.riwayat.'.$certificate->riwaya),
            'grade' => $certificate->grade,
            'grade_label' => __('quran::programs.grades.'.$certificate->grade),
            'examiner' => $this->name($certificate->examiner_id),
            'issued_at' => $this->time->present($certificate->issued_at),
            'revoked' => $certificate->isRevoked(),
            /** The certificate's page to read and print, signed for this reader (for 30 days); null once revoked. */
            'url' => $certificate->isRevoked() ? null : $this->links->page($certificate, $readerId),
            /** Its public verification code (XXXX-XXXX-XXXX-XXXX). */
            'code' => $certificate->isRevoked() ? null : CertificateNumber::display($certificate->code),
        ];
    }

    private function name(?string $userId): ?string
    {
        return $userId === null ? null : (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
