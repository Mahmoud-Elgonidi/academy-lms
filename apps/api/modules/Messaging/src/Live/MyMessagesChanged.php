<?php

namespace Modules\Messaging\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/** "One of your conversations changed" (a message, read elsewhere, locked), on the person's own channel; no data. */
final class MyMessagesChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'messages.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
