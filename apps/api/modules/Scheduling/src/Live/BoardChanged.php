<?php

namespace Modules\Scheduling\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * "Something on this academy's live board changed": no data, only the
 * signal. The boards that are open fetch what they show themselves, with
 * the viewer's rights and time zone.
 */
final class BoardChanged implements ShouldBroadcastNow
{
    public const CHANNEL = 'academy.{academyId}.board';

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
