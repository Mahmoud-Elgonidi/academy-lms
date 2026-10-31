<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\SessionFacts;

/**
 * A finished lesson asks for a report; a student absence asks for a short
 * note (docs/architecture/02, section 5, "حالة التقرير"). Other outcomes ask
 * for nothing, and so does a trial lesson: its teacher assesses it in the
 * CRM instead. Due report_due_hours after the lesson ends, or after now when
 * the lesson was closed late (a review decided days later). Idempotent.
 *
 * A group lesson asks for one report per student in it (docs/architecture/02,
 * "الحصص الجماعية"): a lesson report for each who attended, an absence
 * note for each who did not; a seat excused or owed a makeup asks nothing.
 */
final class OpenSessionReport
{
    public function __construct(private readonly SessionFacts $sessions) {}

    public function handle(string $sessionId, string $outcome): ?SessionReport
    {
        $kind = match ($outcome) {
            'completed' => 'lesson',
            'student_absent' => 'absence',
            default => null,
        };
        $session = $kind === null ? null : $this->sessions->find($sessionId);
        if ($session === null || $session->student_id === null || $session->type === 'trial') {
            return null;
        }

        $ended = CarbonImmutable::parse($session->ends_at);
        $due = $ended->max(CarbonImmutable::now())->addHours(LearningSettings::current()->report_due_hours);
        $open = fn (string $studentId, ?string $enrollmentId, string $kind) => SessionReport::query()->firstOrCreate(['session_id' => $sessionId, 'student_id' => $studentId], [
            'enrollment_id' => $enrollmentId,
            'teacher_id' => $session->teacher_id,
            'kind' => $kind,
            'status' => 'due',
            'session_starts_at' => CarbonImmutable::parse($session->starts_at),
            'due_at' => $due,
        ]);

        if ($session->group_id === null) {
            return $open($session->student_id, $session->enrollment_id, $kind);
        }

        $first = null;
        foreach ($this->sessions->seats($sessionId) as $seat) {
            $seatKind = match ($seat->outcome) {
                'attended' => 'lesson',
                'absent' => 'absence',
                default => null,
            };
            if ($seatKind !== null) {
                $report = $open($seat->student_id, $seat->enrollment_id, $seatKind);
                $first ??= $report;
            }
        }

        return $first;
    }
}
