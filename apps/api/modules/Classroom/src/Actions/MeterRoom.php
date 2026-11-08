<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Modules\Classroom\Models\Room;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Plans\RoomMinutes;
use Modules\SharedKernel\Plans\VideoMeter;

/**
 * A lesson's room on our own video closed: its open time goes into the
 * academy's video minutes (SharedKernel's VideoMeter, which counts each room
 * once, and after it opened again only what it added). A room the provider
 * never said closed is counted, once its lesson is long over, until the
 * lesson's end and the minutes the class stays open after it. An outside
 * link (Zoom, Meet) counts nothing.
 */
final class MeterRoom
{
    public function __construct(private readonly VideoMeter $meter, private readonly AttendanceApi $lessons) {}

    /** @return int the minutes the room counts for, or null when it is not ours to count */
    public function handle(Room $room, ?CarbonImmutable $now = null): ?int
    {
        if ($room->provider !== 'livekit' || $room->started_at === null) {
            return null;
        }
        $lesson = $this->lessons->lesson($room->session_id);
        $finished = $room->finished_at;
        if ($finished === null) {
            if ($lesson === null) {
                return null;
            }
            $finished = $lesson->endsAt->addMinutes((int) config('classroom.closes_after_min'));
            if ($finished->greaterThan($now ?? CarbonImmutable::now())) {
                return null;     // it may still be open: the webhook will say
            }
        }
        $length = $lesson === null ? 0 : (int) round(($lesson->endsAt->getTimestamp() - $lesson->startsAt->getTimestamp()) / 60);
        $minutes = $this->meter->roomClosed(new RoomMinutes($room->id, $room->session_id, $lesson?->teacherId, $room->started_at, $finished, $length));
        // Counted up to here: a later close (the room opened again) is counted again for what it adds.
        $room->forceFill(['metered_at' => $finished])->save();

        return $minutes;
    }
}
