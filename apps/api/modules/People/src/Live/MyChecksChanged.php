<?php

namespace Modules\People\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/** "One of your documents was checked", on the teacher's own channel; no data. */
final class MyChecksChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'checks.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
