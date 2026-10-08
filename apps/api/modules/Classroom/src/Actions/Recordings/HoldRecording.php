<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingWords;
use Modules\SharedKernel\Audit\AuditTrail;

/** The office keeps a recording past its days (a dispute, a family's request), saying why. */
final class HoldRecording
{
    public const REASON_MIN = 10;

    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(Recording $recording, string $byUserId, ?string $reason): Recording
    {
        $reason = trim((string) $reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:300']], [], ['reason' => __('classroom::recordings.attributes.reason')])->validate();
        if ($recording->status === 'deleted') {
            throw ValidationException::withMessages(['recording' => __('classroom::recordings.errors.deleted')]);
        }

        return DB::transaction(function () use ($recording, $byUserId, $reason) {
            $recording->update(['held_at' => now(), 'held_by' => $byUserId, 'hold_reason' => $reason]);
            $this->audit->record('classroom.recording_held', 'recording', $recording->id, ['lesson' => RecordingWords::lesson($recording), 'reason' => $reason]);

            return $recording;
        });
    }
}
