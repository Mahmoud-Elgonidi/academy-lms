<?php

namespace Modules\Platform\Metering;

use Modules\Platform\Domain\Metering\VideoCharge;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\RoomMinutes;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\VideoMeter;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's video minutes for the classroom (docs/architecture/02,
 * section 11): a closed room on our own video goes into the ledger at its
 * actual open time (VideoCharge: rounded up, at most the lesson and 15
 * minutes), once per room and, after it opened again, only what it added.
 * The limit is soft unless the platform team set the hard one: then, with
 * nothing left (the rooms open now counted too), new lessons open on the
 * teacher's outside link. A lesson is never cut.
 */
final class PlatformVideoMeter implements VideoMeter
{
    public function __construct(
        private readonly Meters $meters,
        private readonly TenantContext $tenants,
        private readonly UsageMeters $usage,
    ) {}

    public function ownVideoAllowed(): bool
    {
        $academyId = $this->tenants->id();
        if ($academyId === null || ! AcademyMetering::for($academyId)->video_hard_limit) {
            return true;
        }
        $state = $this->meters->state($academyId, Meter::VideoMinutes);

        return ! $state->limited || $state->available() - $this->usage->runningNow(Limit::VideoMinutes) > 0;
    }

    public function roomClosed(RoomMinutes $room): int
    {
        $academyId = $this->tenants->id();
        if ($academyId === null) {
            return 0;
        }
        $minutes = VideoCharge::minutes($room->startedAt, $room->finishedAt, $room->lessonMinutes);
        $this->meters->consumeUpTo($academyId, Meter::VideoMinutes, $minutes, 'room', $room->roomId, [
            'subject' => 'lesson',
            'user_id' => $room->teacherId,
            'occurred_at' => $room->finishedAt,
        ]);

        return $minutes;
    }
}
