<?php

namespace Modules\Quran\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Quran\Events\CertificateIssued;
use Modules\Quran\Events\ExamBooked;
use Modules\Quran\Events\ExamDecided;
use Modules\Quran\Events\ExamSuggested;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The Quran's messages (wording in lang/{ar,en}/notifications.php): an exam
 * due goes to who examines the Quran; a booked exam to the student and their
 * guardians and to its examiner; a result to the family; a certificate to
 * the family with its own page (each reader's signed link). Never the
 * examiner's words or the mistakes: those are read in the app.
 */
final class SendQuranNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly PeopleApi $people,
        private readonly AttendanceApi $lessons,
        private readonly Stops $stops,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'quran::notifications.audience.';
        $about = ['student' => 'زياد', 'stop' => 'سورة النبأ'];
        foreach ([
            ['exam_suggested', 'staff', $a.'examiners', ['push', 'email'], $about],
            ['exam_booked', 'lessons', $a.'family', ['push', 'whatsapp', 'email'], $about + ['examiner' => 'منى', 'when' => 'الأحد 5 أكتوبر · 6:00 م']],
            ['exam_assigned', 'staff', $a.'examiner', ['push', 'email'], $about + ['when' => 'الأحد 5 أكتوبر · 6:00 م']],
            ['exam_passed', 'lessons', $a.'family', ['push', 'whatsapp', 'email'], $about + ['grade' => 'ممتاز']],
            ['exam_failed', 'lessons', $a.'family', ['push', 'whatsapp', 'email'], $about + ['grade' => 'يُعاد', 'day' => 'الأحد 12 أكتوبر']],
            ['certificate_issued', 'lessons', $a.'family', ['push', 'whatsapp', 'email'], $about + ['number' => 'Q-2026-000123', 'link' => 'https://…']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "quran.{$name}", category: $category,
                title: "quran::notifications.{$name}.title", body: "quran::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function suggested(ExamSuggested $event): void
    {
        $exam = $this->exam($event->examId);
        if ($exam === null) {
            return;
        }
        $this->notifications->send(new Message('quran.exam_suggested', $this->identity->usersWithAbility(Ability::ExamineQuran), $this->about($exam),
            ['screen' => 'quran_exam', 'id' => $exam->id], "quran-exam-suggested:{$exam->id}"));
    }

    public function booked(ExamBooked $event): void
    {
        $exam = $this->exam($event->examId);
        $lesson = $exam?->session_id === null ? null : $this->lessons->lesson((string) $exam->session_id);
        if ($exam === null || $lesson === null) {
            return;
        }
        $data = $this->about($exam) + ['when' => $lesson->startsAt];
        $action = ['screen' => 'quran_exam', 'id' => $exam->id];
        $this->notifications->send(new Message('quran.exam_booked', $this->family($exam->student_id), $data + ['examiner' => $this->name($exam->examiner_id)],
            $action, "quran-exam-booked:{$exam->id}:{$exam->session_id}"));
        $this->notifications->send(new Message('quran.exam_assigned', [(string) $exam->examiner_id], $data, $action, "quran-exam-assigned:{$exam->id}:{$exam->session_id}"));
    }

    public function decided(ExamDecided $event): void
    {
        $exam = $this->exam($event->examId);
        if ($exam === null || ! in_array($exam->status, ['passed', 'failed'], true)) {
            return;
        }
        $data = $this->about($exam) + ['grade' => new Translatable('quran::programs.grades.'.$exam->grade)];
        if ($exam->status === 'failed') {
            $data['day'] = new Day((string) $exam->retake_on?->toDateString());
        }
        $this->notifications->send(new Message("quran.exam_{$exam->status}", $this->family($exam->student_id), $data,
            ['screen' => 'quran_exam', 'id' => $exam->id], "quran-exam-decided:{$exam->id}"));
    }

    public function issued(CertificateIssued $event): void
    {
        $certificate = QuranCertificate::query()->find($event->certificateId);
        if ($certificate === null || $certificate->isRevoked()) {
            return;
        }
        $portion = $certificate->portion;
        $this->notifications->send(new Message('quran.certificate_issued', $this->family($certificate->student_id), [
            'student' => $this->name($certificate->student_id),
            'stop' => $this->stops->localized((string) $portion['kind'], isset($portion['number']) ? (int) $portion['number'] : null, (string) $portion['program']),
            'number' => $certificate->number,
        ], ['screen' => 'quran_certificate', 'id' => $certificate->id], "quran-certificate:{$certificate->id}"));
    }

    private function exam(string $id): ?QuranExam
    {
        return QuranExam::query()->with('program')->find($id);
    }

    /** @return array<string, mixed> */
    private function about(QuranExam $exam): array
    {
        return ['student' => $this->name($exam->student_id), 'stop' => $this->stops->localized($exam->stop_kind, $exam->stop_number, $exam->program->name)];
    }

    /** @return list<string> the student and every guardian of theirs */
    private function family(string $studentId): array
    {
        return array_values(array_unique([$studentId, ...$this->people->guardiansOf($studentId)]));
    }

    private function name(?string $userId): string
    {
        return $userId === null ? '' : (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
