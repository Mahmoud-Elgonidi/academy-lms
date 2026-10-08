<?php

namespace Modules\Classroom\Video;

use Modules\Classroom\Models\Room;

/**
 * A provider that can record its rooms (LiveKit, through Egress). An outside
 * link cannot: the academy records nothing there.
 */
interface Recorder
{
    /** Starts recording the room into the output; returns the provider's id for the job. Throws when it cannot. */
    public function startRecording(Room $room, RecordingOutput $output): string;

    /** Stops the job; the file is finished and reported by the provider's webhook. */
    public function stopRecording(string $jobId): void;

    /** Tells everyone in the room whether it is being recorded (their pages show it). */
    public function announceRecording(Room $room, bool $recording): void;
}
