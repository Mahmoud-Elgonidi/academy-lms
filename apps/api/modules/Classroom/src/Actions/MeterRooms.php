<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Modules\Classroom\Models\Room;
use Throwable;

/**
 * Sweeper (every minute, with the reconcile): the rooms on our own video
 * whose close was not counted yet (a webhook missed or failed, a room that
 * opened again and closed), and the ones the provider never said closed
 * whose lesson is long over, counted into the month's video minutes.
 */
final class MeterRooms
{
    /** How far back a missed room is still counted. */
    public const DAYS = 35;

    public function __construct(private readonly MeterRoom $meter) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $rooms = Room::query()->where('provider', 'livekit')->whereNotNull('started_at')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNotNull('finished_at')->where('finished_at', '>=', $now->subDays(self::DAYS))
                    ->where(fn ($q) => $q->whereNull('metered_at')->orWhereColumn('metered_at', '<', 'finished_at')))
                ->orWhere(fn ($q) => $q->whereNull('finished_at')->whereNull('metered_at')->where('started_at', '>=', $now->subDays(self::DAYS))->where('started_at', '<', $now->subMinutes(30))))
            ->limit(200)->get();
        $counted = 0;
        foreach ($rooms as $room) {
            try {
                $counted += $this->meter->handle($room, $now) === null ? 0 : 1;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $counted;
    }
}
