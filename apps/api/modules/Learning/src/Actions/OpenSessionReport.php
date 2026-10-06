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
        $from = $ended->max(CarbonImmutable::now());

        return SessionReport::query()->firstOrCreate(['session_id' => $sessionId], [
            'enrollment_id' => $session->enrollment_id,
            'teacher_id' => $session->teacher_id,
            'student_id' => $session->student_id,
            'kind' => $kind,
            'status' => 'due',
            'session_starts_at' => CarbonImmutable::parse($session->starts_at),
            'due_at' => $from->addHours(LearningSettings::current()->report_due_hours),
        ]);
    }
}
