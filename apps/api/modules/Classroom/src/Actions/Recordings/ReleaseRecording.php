<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\DB;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Recordings\RecordingWords;
use Modules\SharedKernel\Audit\AuditTrail;

/** The office lets a held recording go again: it keeps its own days (and goes at the next sweep if they are up). */
final class ReleaseRecording
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(Recording $recording): Recording
    {
        if ($recording->held_at === null) {
            return $recording;
        }

        return DB::transaction(function () use ($recording) {
            $recording->update(['held_at' => null, 'held_by' => null, 'hold_reason' => null]);
            $this->audit->record('classroom.recording_released', 'recording', $recording->id, ['lesson' => RecordingWords::lesson($recording)]);

            return $recording;
        });
    }
}
