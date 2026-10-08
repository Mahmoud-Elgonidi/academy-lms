<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\Room;
use Modules\Scheduling\Contracts\AttendanceApi;
use Throwable;

/**
 * When the academy records every lesson: once the teacher and the student
 * are both in the room, recording starts by itself (if everyone agreed). It
 * does not start again in a lesson where someone stopped it.
 */
final class RecordWhenBothIn
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly StartRecording $start,
    ) {}

    public function handle(Room $room): ?Recording
    {
        if (ClassroomSettings::current()->recording !== 'auto' || $room->finished_at !== null) {
            return null;
        }
        $lesson = $this->attendance->lesson($room->session_id);
        if ($lesson === null || $lesson->studentId === null) {
            return null;
        }
        $in = $this->attendance->inClassroom($room->session_id);
        if (! in_array($lesson->teacherId, $in, true) || ! in_array($lesson->studentId, $in, true)) {
            return null;
        }
        if (Recording::query()->where('session_id', $room->session_id)->where(fn ($q) => $q->whereIn('status', Recording::RUNNING)->orWhereNotNull('stopped_by'))->exists()) {
            return null;
        }

        try {
            return $this->start->handle($room->session_id, null);
        } catch (ValidationException $e) {
            // Someone has not agreed, or the lesson is not open: it is not recorded, as it should be.
            return null;
        } catch (Throwable $e) {
            Log::warning("Automatic recording did not start for {$room->name}: {$e->getMessage()}");

            return null;
        }
    }
}
