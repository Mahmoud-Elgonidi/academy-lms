<?php

namespace Modules\Learning\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Learning\Contracts\Events\HomeworkAssigned;
use Modules\Learning\Contracts\Events\HomeworkReviewed;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\Events\SessionReportOverdue;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\People\Contracts\PeopleApi;

/**
 * What the family and the teacher hear about reports, homework and progress
 * (docs/architecture/01-features.md, sections 10 and 15). The wording is in
 * lang/{ar,en}/notifications.php; academies may reword it.
 */
final class SendLearningNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PeopleApi $people,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $family = 'learning::notifications.audience.family';
        $teacher = 'learning::notifications.audience.teacher';
        foreach ([
            ['report_submitted', 'learning', $family, ['push', 'whatsapp'], ['student' => 'زياد', 'teacher' => 'أ. خالد', 'when' => 'الأحد 4 أكتوبر · 18:00', 'overall' => '4', 'note' => 'تحسن واضح في التذكير والتأنيث.']],
            ['homework_assigned', 'learning', $family, ['push', 'whatsapp'], ['student' => 'زياد', 'title' => 'صرّف الفعل كتب', 'due' => 'الخميس 8 أكتوبر · 18:00']],
            ['homework_reviewed', 'learning', $family, ['push'], ['student' => 'زياد', 'title' => 'صرّف الفعل كتب', 'grade' => '4', 'feedback' => 'ممتاز!']],
            ['node_mastered', 'learning', $family, [], ['student' => 'زياد', 'lesson' => 'الفعل الماضي', 'percent' => '30', 'program' => 'النحو الميسّر']],
            ['homework_submitted', 'staff', $teacher, ['push'], ['student' => 'زياد', 'title' => 'صرّف الفعل كتب']],
            ['report_overdue', 'staff', $teacher, ['push', 'whatsapp'], ['student' => 'زياد', 'when' => 'الأحد 4 أكتوبر · 18:00']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "learning.{$name}", category: $category,
                title: "learning::notifications.{$name}.title", body: "learning::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function reportSubmitted(SessionReportSubmitted $event): void
    {
        $report = SessionReport::query()->find($event->reportId);
        if ($report === null || $report->kind !== 'lesson') {
            return;
        }

        $this->notifications->send(new Message('learning.report_submitted', $this->family($report->student_id), [
            'student' => $this->name($report->student_id),
            'teacher' => $this->name($report->teacher_id),
            'when' => $report->session_starts_at,
            'overall' => $report->overall,
            'note' => Str::limit((string) $report->notes_for_student, 300),
        ], ['screen' => 'report', 'id' => $report->id, 'enrollment_id' => (string) $report->enrollment_id], "report:{$report->id}"));
    }

    public function homeworkAssigned(HomeworkAssigned $event): void
    {
        $homework = Homework::query()->find($event->homeworkId);
        if ($homework === null) {
            return;
        }

        $this->notifications->send(new Message('learning.homework_assigned', $this->family($homework->student_id), [
            'student' => $this->name($homework->student_id),
            'title' => $homework->title,
            'due' => $homework->due_at,
        ], ['screen' => 'homework', 'id' => $homework->id], "homework:{$homework->id}"));
    }

    public function homeworkReviewed(HomeworkReviewed $event): void
    {
        $homework = Homework::query()->find($event->homeworkId);
        if ($homework === null) {
            return;
        }

        $this->notifications->send(new Message('learning.homework_reviewed', $this->family($homework->student_id), [
            'student' => $this->name($homework->student_id),
            'title' => $homework->title,
            'grade' => $homework->grade ?? new Translatable('learning::notifications.no_grade'),
            'feedback' => Str::limit((string) $homework->feedback, 300),
        ], ['screen' => 'homework', 'id' => $homework->id], "homework-marked:{$homework->id}"));
    }

    public function nodeMastered(NodeMastered $event): void
    {
        $map = app(RoadmapQuery::class)->for($event->enrollmentId);
        $lesson = DB::table('curriculum_nodes')->where('id', $event->nodeId)->first(['title_ar', 'title_en']);
        $program = DB::table('programs')->where('id', $map['program']['id'] ?? null)->first(['name_ar', 'name_en']);
        if ($map === null || $lesson === null || $program === null) {
            return;
        }

        $this->notifications->send(new Message('learning.node_mastered', $this->family($event->studentId), [
            'student' => $this->name($event->studentId),
            'lesson' => new Localized($lesson->title_ar, $lesson->title_en),
            'percent' => $map['percent'],
            'program' => new Localized($program->name_ar, $program->name_en),
        ], ['screen' => 'roadmap', 'enrollment_id' => $event->enrollmentId], "mastered:{$event->enrollmentId}:{$event->nodeId}"));
    }

    public function homeworkSubmitted(HomeworkSubmitted $event): void
    {
        $homework = Homework::query()->find($event->homeworkId);
        if ($homework === null) {
            return;
        }

        $this->notifications->send(new Message('learning.homework_submitted', [$homework->teacher_id], [
            'student' => $this->name($homework->student_id),
            'title' => $homework->title,
        ], ['screen' => 'homework_to_mark', 'id' => $homework->id]));
    }

    public function reportOverdue(SessionReportOverdue $event): void
    {
        $report = SessionReport::query()->find($event->reportId);
        if ($report === null) {
            return;
        }

        $this->notifications->send(new Message('learning.report_overdue', [$report->teacher_id], [
            'student' => $this->name($report->student_id),
            'when' => $report->session_starts_at,
        ], ['screen' => 'write_report', 'id' => $report->id], "report-overdue:{$report->id}"));
    }

    /** @return list<string> the student and the guardians who asked for reports */
    private function family(string $studentId): array
    {
        return [$studentId, ...$this->people->guardiansOf($studentId, reportsOnly: true)];
    }

    private function name(string $userId): string
    {
        return (string) DB::table('users')->where('id', $userId)->value('name');
    }
}
