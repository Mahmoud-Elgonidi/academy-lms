<?php

namespace Modules\Scheduling\Live;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\Identity\Contracts\PersonalChannel;

/**
 * "One of your lessons changed" (its time, status or teacher, or a student
 * booked in or out), on the person's own channel: the teacher's, the
 * student's and their guardians'. No data.
 */
final class LessonsChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $userId) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(PersonalChannel::name($this->userId))];
    }

    public function broadcastAs(): string
    {
        return 'lessons.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
