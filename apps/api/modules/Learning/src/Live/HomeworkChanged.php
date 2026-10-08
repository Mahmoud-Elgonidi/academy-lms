<?php

namespace Modules\Learning\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/**
 * "Homework of yours changed" (given, handed in, marked), on the person's own
 * channel: the teacher's, the student's and their guardians'. No data.
 */
final class HomeworkChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'homework.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
