<?php

namespace Modules\Evaluation\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\Models\KpiExclusion;

/**
 * Takes a lesson out of its teacher's numbers, with the reason (docs/
 * architecture/02, section 7: what the platform or the video provider
 * caused does not count against the teacher); or puts it back.
 */
final class ExcludeLesson
{
    public function handle(string $sessionId, string $reason, ?string $note, string $actorId): KpiExclusion
    {
        if (! in_array($reason, KpiExclusion::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('evaluation::kpis.errors.reason')]);
        }
        $teacherId = DB::table('sessions')->where('id', $sessionId)->value('teacher_id')
            ?? throw ValidationException::withMessages(['session' => __('evaluation::kpis.errors.lesson')]);

        return KpiExclusion::query()->updateOrCreate(['session_id' => $sessionId], [
            'teacher_id' => $teacherId, 'reason' => $reason, 'note' => blank($note) ? null : trim((string) $note), 'excluded_by' => $actorId,
        ]);
    }

    public function undo(string $sessionId): void
    {
        KpiExclusion::query()->where('session_id', $sessionId)->delete();
    }
}
