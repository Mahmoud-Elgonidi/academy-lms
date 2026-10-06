<?php

namespace Modules\SharedKernel\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * The relay's heartbeat on a public channel, with no data: "alive" every
 * minute, so a live screen knows its signals still come (else it falls back
 * to a timer), and "resync" when the relay starts, so every open screen
 * catches up on what it may have missed.
 */
final class RelayPulse implements ShouldBroadcastNow
{
    public const CHANNEL = 'lms.live';

    public function __construct(public readonly bool $resync = false) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel(self::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return $this->resync ? 'resync' : 'alive';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
