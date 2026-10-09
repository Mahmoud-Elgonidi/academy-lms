<?php

namespace Modules\Safeguarding\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/** "A concern you raised moved on" (being looked into, closed), on the reporter's own channel; no data. */
final class MyConcernsChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'concerns.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
