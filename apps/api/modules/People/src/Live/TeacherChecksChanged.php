<?php

namespace Modules\People\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** "A teacher's document of this academy changed", for the open checks screens; no data. */
final class TeacherChecksChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'academy.{academyId}.checks';

    public function __construct(public readonly string $academyId) {}

    public static function channel(string $academyId): string
    {
        return str_replace('{academyId}', $academyId, self::CHANNEL);
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channel($this->academyId))];
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
