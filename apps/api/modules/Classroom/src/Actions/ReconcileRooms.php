<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\ProviderManager;
use Modules\Scheduling\Contracts\AttendanceApi;
use Throwable;

/**
 * Sweeper (every minute): asks the provider who is in each open lesson's
 * room and fixes attendance a lost webhook left wrong, before the deadlines
 * decide on it (docs/architecture/03, section 6: webhooks reconciled with
 * the room API).
 */
final class ReconcileRooms
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly ProviderManager $providers,
    ) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $lessons = $this->attendance->lessonsInClass($now, config('classroom.opens_before_min'), config('classroom.closes_after_min'));
        $fixed = 0;

        foreach (Room::query()->whereIn('session_id', $lessons)->whereNull('finished_at')->get() as $room) {
            try {
                $present = $this->providers->get($room->provider)->participants($room);
            } catch (Throwable $e) {
                Log::warning("Classroom reconcile skipped {$room->name}: {$e->getMessage()}");

                continue;
            }
            if ($present === null) {
                continue;   // the provider cannot tell (outside link)
            }
            $lesson = $this->attendance->lesson($room->session_id);
            $people = array_values(array_intersect($present, array_filter([$lesson?->teacherId, $lesson?->studentId])));
            $open = $this->attendance->inClassroom($room->session_id);

            foreach (array_diff($people, $open) as $userId) {
                $this->attendance->participantJoined($room->session_id, $userId, $now, 'reconcile');
                $fixed++;
            }
            foreach (array_diff($open, $people) as $userId) {
                $this->attendance->participantLeft($room->session_id, $userId, $now, 'reconcile');
                $fixed++;
            }
        }

        return $fixed;
    }
}
