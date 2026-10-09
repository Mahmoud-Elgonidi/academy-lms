<?php

namespace Modules\Safeguarding\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** "A concern of this academy changed", for the leads' open screens; no data. */
final class ConcernsChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'academy.{academyId}.safeguarding';

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
