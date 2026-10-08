<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingStore;
use Modules\Classroom\Recordings\RecordingWords;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The office removes a recording before its days are up (a family asked),
 * saying why: the file goes, the row stays, marked deleted. One that is held
 * (by the office, or a complaint still open about its lesson) or still being
 * made cannot be removed.
 */
final class DeleteRecording
{
    public function __construct(
        private readonly RecordingStore $store,
        private readonly RecordingHolds $holds,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(Recording $recording, string $byUserId, ?string $reason): Recording
    {
        $reason = trim((string) $reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:'.HoldRecording::REASON_MIN, 'max:300']], [], ['reason' => __('classroom::recordings.attributes.reason')])->validate();
        $refusal = match (true) {
            $recording->status === 'deleted' => 'deleted',
            $recording->isRunning() => 'running',
            $recording->held_at !== null => 'held',
            isset($this->holds->heldSessions([$recording->session_id])[$recording->session_id]) => 'complaint',
            default => null,
        };
        if ($refusal !== null) {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.'.$refusal)]);
        }

        DB::transaction(function () use ($recording, $byUserId, $reason) {
            $recording->update(['status' => 'deleted', 'deleted_at' => now(), 'deleted_by' => $byUserId, 'delete_reason' => $reason]);
            $this->audit->record('classroom.recording_deleted', 'recording', $recording->id, ['lesson' => RecordingWords::lesson($recording), 'reason' => $reason]);
        });
        $this->store->delete($recording);

        return $recording;
    }
}
